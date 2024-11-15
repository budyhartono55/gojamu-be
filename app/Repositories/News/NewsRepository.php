<?php

namespace App\Repositories\News;

use App\Helpers\Helper;
use App\Models\Ctg_News;
use App\Models\News;
use App\Repositories\News\NewsInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class NewsRepository implements NewsInterface
{
    private $news;
    // 1 Minute redis expired
    private $expired = 360;
    private $generalRedisKeys = 'News-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(News $news)
    {
        $this->news = $news;
    }

    public function getNews($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getSearch = $request->search;
            $getByCategory = $request->category;
            $getByFilter = $request->filter;
            $getRead = $request->read;
            $getById = $request->id;
            $getTrash = $request->trash;
            $getEvent = $request->event;
            $page = $request->page;
            $paginate = $request->paginate;
            // $clientIpAddress = $request->getClientIp();

            $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Category=" . $getByCategory . ",#Event=" . $getEvent . ",#Read=" . $getRead . ",#Search=" . $getSearch;

            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Berita By {$params} from (CACHE)", $result);
            }
            $sqlQuery = News::with(['ctg_news'])->orderBy('posted_at', $order);
            // Step 3: Set the query based on trash filter
            if ($request->filled('trash') && $request->trash == "true") {
                $query = $sqlQuery->onlyTrashed();
            } else {
                $query = $sqlQuery;
            }

            // Step 4: Apply search filter
            if ($request->filled('search')) {
                $query->where('berita_title', 'LIKE', '%' . $getSearch . '%');
            }

            // Step 5: Apply category filter
            if ($request->filled('category')) {
                $query->whereHas('categories', function ($queryCategory) use ($request) {
                    return $queryCategory->where('slug', Str::slug($request->category));
                });
            }

            // Step 6: Apply custom filter for sorting
            if ($request->filled('filter')) {
                $filter = $getByFilter == "top" ? 'views' : 'posted_at';
                $query->orderBy($filter, $order);
            }

            // Step 7: Apply read filter and increment views if not already viewed
            if ($request->filled('read')) {
                $newsItem = $query->where('slug', $getRead)->first();

                if ($newsItem) {
                    if (!session()->has('viewed_book_' . $getRead)) {
                        // Increment the views count if the book hasn't been viewed before in this session
                        $newsItem->increment('views');
                        session()->put('viewed_book_' . $getRead, true);  // Mark the book as viewed
                    }
                }
            }

            // Step 8: Apply id filter and increment views if not already viewed
            if ($request->filled('id')) {
                $newsItem = $query->where('id', $getById)->first();

                if ($newsItem) {
                    if (!session()->has('viewed_book_' . $getRead)) {
                        // Increment the views count if the book hasn't been viewed before in this session
                        $newsItem->increment('views');
                        session()->put('viewed_book_' . $getRead, true);  // Mark the book as viewed
                    }
                }
            }

            // Step 9: Paginate or limit the results
            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }

            // Step 10: Modify the result (optional)
            $datas = Self::queryGetModify($result, $setPaginate, true);

            // Step 11: Cache the results in Redis
            Redis::set($key, json_encode($datas));
            Redis::expire($key,  $this->expired);

            return $this->success("List Berita By {$params}", $datas);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!" . $e->getMessage(), "");
        }
    }


    public function save($request)
    {
        // Step 1: Validation
        $validator = Validator::make($request->all(), [
            'berita_title' => 'required|string',
            'description'  => 'required|string',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'ctg_news_id'   => 'required|exists:ctg_news,id',
            'posted_at'     => 'required|date_format:d-m-Y'
        ]);

        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            // Step 2: Check if category exists
            $ctg_news_id = Ctg_News::find($request->ctg_news_id);
            if (!$ctg_news_id) {
                return $this->error("Not Found", "Category Berita dengan ID = ($request->ctg_news_id) tidak ditemukan!", 404);
            }

            // Step 3: Handle file upload
            $fileName = '';
            if ($request->hasFile('image')) {
                $fileName = "news_" . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension();
                // Save image in storage
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
            }

            // Step 4: Prepare data
            $data = [
                'berita_title' => $request->berita_title,
                'description'  => $request->description,
                'image'         => $fileName,
                'slug'          => Str::slug($request->berita_title),
                'ctg_news_id'   => $request->ctg_news_id,
                'user_id'       => Auth::user()->id,
                'created_by'    => Auth::user()->id,
                'posted_at'     => Carbon::createFromFormat('d-m-Y', $request->posted_at)
            ];

            // Step 5: Create Berita (news)
            $add = News::create($data);

            if ($add) {
                // Step 6: Clear Redis cache after insertion
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Berita Berhasil ditambahkan!", $data);
            }

            return $this->error("FAILED", "Berita gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // Step 7: Handle unexpected errors
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        // Step 1: Validate the request
        $validator = Validator::make($request->all(), [
            'berita_title' => 'required|string',
            'description'  => 'required|string',
            'image'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'ctg_news_id'   => 'required|exists:ctg_news,id',
            'posted_at'     => 'required|date_format:d-m-Y',
        ]);

        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            // Step 2: Check if the category exists
            $ctg_news_id = Ctg_News::find($request->ctg_news_id);
            if (!$ctg_news_id) {
                return $this->error("Not Found", "Category Berita dengan ID = ($request->ctg_news_id) tidak ditemukan!", 404);
            }

            // Step 3: Find the news record by ID
            $datas = News::find($id);
            if (!$datas) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 4: Update news fields
            $datas->berita_title = $request->berita_title;
            $datas->description = $request->description;
            $datas->slug = Str::slug($request->berita_title);
            $datas->views = $datas->views;  // Keeping the views count intact
            $datas->ctg_news_id = $request->ctg_news_id;
            $datas->user_id = Auth::user()->id;
            $datas->edited_by = Auth::user()->id;
            $datas->posted_at = Carbon::createFromFormat('d-m-Y', $request->posted_at);

            // Step 5: Handle image upload or deletion
            if ($request->hasFile('image')) {
                // Delete old image if there's a new one
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                // Generate new file name and update the image field
                $fileName = 'news_' . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension();
                $datas->image = $fileName;

                // Save the new image to public storage
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
            } elseif ($request->delete_image) {
                // Delete image if the delete_image flag is set
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);
                $datas->image = null;
            }

            // Step 6: Save the updated news record
            if ($datas->save()) {
                // Step 7: Clear the Redis cache after saving
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Berita Berhasil diperbaharui!", $datas);
            }

            return $this->error("FAILED", "Berita gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // Handle any unexpected errors
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // Step 1: Find the news by ID
            $data = News::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 2: Attempt to delete the news
            if ($data->delete()) {
                // Step 3: Clear related Redis cache after successful deletion
                Helper::deleteRedis($this->generalRedisKeys . "*");

                // Step 4: Return success response
                return $this->success("COMPLETED", "Berita dengan ID = ($id) Berhasil dihapus!");
            }

            // Step 5: If deletion fails, return an error response
            return $this->error("FAILED", "Berita dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // Step 6: Log the error for debugging purposes
            Log::error("Error deleting berita with ID = ($id): " . $e->getMessage(), ['exception' => $e]);

            // Step 7: Return generic error message for internal server error
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {
            // Step 1: Find the trashed news item by ID
            $data = News::onlyTrashed()->find($id);

            // Step 2: If the record is not found, return a 404 error
            if (!$data) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan dalam sampah!", 404);
            }

            // Step 3: Permanently delete the news item
            if ($data->forceDelete()) {
                // Step 4: Delete the associated image if available
                if ($data->image) {
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);
                }

                // Step 5: Clear related Redis cache after successful deletion
                Helper::deleteRedis($this->generalRedisKeys . "*");

                // Step 6: Return success response after deletion
                return $this->success("COMPLETED", "Berita dengan ID = ($id) Berhasil dihapus permanen!");
            }

            // Step 7: If deletion fails, return an error response
            return $this->error("FAILED", "Berita dengan ID = ($id) Gagal dihapus permanen!", 400);
        } catch (Exception $e) {
            // Step 8: Log the error for debugging purposes
            Log::error("Error permanently deleting berita with ID = ($id): " . $e->getMessage(), ['exception' => $e]);

            // Step 9: Return generic error message for internal server error
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            // Get all trashed records
            $data = News::onlyTrashed();

            // Check if there are any trashed records to restore
            if ($data->isEmpty()) {
                return $this->error("Not Found", "Tidak ada Berita yang ada di sampah untuk dipulihkan!", 404);
            }

            // Restore all trashed records
            $restored = $data->restore();

            // Check if restore was successful
            if ($restored) {
                // Clear Redis cache after successful restore
                Helper::deleteRedis($this->generalRedisKeys . "*");

                // Return success response
                return $this->success("COMPLETED", "Semua Berita di sampah telah berhasil dipulihkan!");
            }

            // If restore failed, return an error response
            return $this->error("FAILED", "Restore Berita gagal!", 400);
        } catch (Exception $e) {
            // Log error and return an internal server error response
            Log::error("Error restoring all trashed berita: " . $e->getMessage(), ['exception' => $e]);
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            // Step 1: Find the trashed news item by ID
            $data = News::onlyTrashed()->where('id', $id)->first();

            // Step 2: If the record is not found, return a 404 error
            if (!$data) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan dalam sampah!", 404);
            }

            // Step 3: Restore the trashed news item
            if ($data->restore()) {
                // Step 4: Clear related Redis cache after successful restore
                Helper::deleteRedis($this->generalRedisKeys . "*");

                // Step 5: Return success response after restoration
                return $this->success("COMPLETED", "Berita dengan ID = ($id) Berhasil dipulihkan!");
            }

            // Step 6: If restoration fails, return an error response
            return $this->error("FAILED", "Restore Berita dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // Step 7: Log the error for debugging purposes
            Log::error("Error restoring berita with ID = ($id): " . $e->getMessage(), ['exception' => $e]);

            // Step 8: Return generic error message for internal server error
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    // function addViews($id_berita)
    // {
    //     $datas = News::find($id_berita);
    //     $datas['views'] = $datas->views + 1;
    //     return $datas->save();
    // }

    // function query($kondisi = "posted_at")
    // {
    //     return News::latest($kondisi == "views" ? 'views' : 'posted_at')
    //         ->select(['news.*']);
    // }

    function queryGetCategory($id)
    {
        return ctg_news::find($id);
    }


    function queryGetModify($datas, $paginate, $manyResult = false)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');

                $modifiedData = array_map(function ($item) {
                    // $item->berita_link = env('NEWS_LINK') . $item->slug;
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
                // return $datas;
                self::modifyData($datas);
            }
            return $datas;
        }
    }

    function modifyData($item)
    {

        // $ctg_news_id = [
        //     'id' => $item['ctg_news_id'],
        //     'name' => self::queryGetCategory($item['ctg_news_id'])->title_category,
        //     'slug' => self::queryGetCategory($item['ctg_news_id'])->slug,
        // ];
        // $item->ctg_news_id = $ctg_news_id;

        // $user_id = [
        //     'name' => Helper::queryGetUser($item['user_id']),
        // ];
        // $item->user_id = $user_id;
        // $item->image = Helper::convertImageToBase64('images/', $item->image);
        // $item = Helper::queryGetUserModify($item);
        $item->created_by = optional($item->createdBy)->only(['id', 'name']);
        $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

        unset($item->createdBy, $item->editedBy, $item->deleted_at);

        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }
}
