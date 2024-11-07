<?php

namespace App\Repositories\Book;

use App\Helpers\Helper;
use App\Models\Ctg_Book;
use App\Models\Event_Program;
use App\Models\Book;
use App\Models\User;
use App\Repositories\Book\BookInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class BookRepository implements BookInterface
{
    private $books;
    // 1 Minute redis expired
    private $expired = 360;
    private $generalRedisKeys = 'Book-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    private $destinationFile = "files";
    use API_response;

    public function __construct(Book $books)
    {
        $this->books = $books;
    }

    public function getBook($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request, 18);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            // Step 3: Extract other query parameters
            $getSearch = $request->search;
            $getByCategory = $request->category;
            $getByFavorite = $request->favorite;
            $getByTopic = $request->topic;
            $getByFilter = $request->filter;
            $getRead = $request->read;
            $getById = $request->id;
            $getTrash = $request->trash;
            $getEvent = $request->event;
            $page = $request->page;
            $paginate = $request->paginate;
            $clientIpAddress = $request->getClientIp();

            // Step 4: Construct cache key
            $params = "#id={$getById},#Trash={$getTrash},#Paginate={$paginate},#Order={$order},#Limit={$limit},#Page={$page},#Category={$getByCategory},#Topic={$getByTopic},#Favorite={$getByFavorite},#Event={$getEvent},#Read={$getRead},#Search={$getSearch}";
            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . $clientIpAddress . "#params" . $params;

            // Step 5: Check if cache exists
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Berita By {$params} from (CACHE)", $result);
            }

            // Step 6: Build the query
            if ($getTrash === "true") {
                $query = Book::onlyTrashed()->with(['topics', 'ctg_book', 'favorite', 'user'])->orderBy('posted_at', $order);
            } else {
                $query = Book::with(['topics', 'ctg_book', 'favorite', 'user'])->orderBy('posted_at', $order);
            }

            // Step 7: Apply filters if provided
            if ($getEvent) {
                $query->whereHas('event', function ($queryEvent) use ($getEvent) {
                    $queryEvent->where('slug', Str::slug($getEvent));
                });
            }

            if ($getSearch) {
                $query->where('berita_title', 'LIKE', '%' . $getSearch . '%');
            }

            if ($getByCategory) {
                $query->whereHas('categories', function ($queryCategory) use ($getByCategory) {
                    $queryCategory->where('slug', Str::slug($getByCategory));
                });
            }

            if ($getByFilter) {
                $filter = ($getByFilter == "top") ? 'views' : 'posted_at';
                $query->orderBy($filter, $order);
            }

            if ($getRead) {
                $query->where('slug', $getRead);
                if ($query->first()) {
                    $this->addViews($query->first()->id); // Add views to the book
                }
            }

            if ($getById) {
                $query->where('id', $getById);
            }

            // Step 8: Handle pagination
            if ($paginate === "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }

            // Step 9: Modify query result (if needed)
            $datas = Self::queryGetModify($result, $setPaginate, true);

            // Step 10: Cache the result for later use
            Redis::set($key, json_encode($datas));
            Redis::expire($key, $this->expired);

            // Step 11: Return success response
            return $this->success("List Berita By {$params}", $datas);
        } catch (\Exception $e) {
            // Handle exceptions and return error response
            return $this->error("Internal Server Error: " . $e->getMessage(), "");
        }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'berita_title'     => 'required',
            'description'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'file'           => 'mimes:pdf|max:10240',
            'category_id'  => 'required',
            'posted_at' => 'required',
            'topic_id' => 'required|array', // Array of topic IDs
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $category_id = Ctg_Book::find($request->category_id);
            // check
            if (!$category_id) {
                return $this->error("Not Found", "Category Buku dengan ID = ($request->category_id) tidak ditemukan!", 404);
            }


            $fileName = $request->hasFile('file') ? "books_" . time() . "-" . Str::slug($request->file->getClientOriginalName()) . "." . $request->file->getClientOriginalExtension() : "";
            $coverName = $request->hasFile('cover') ? "books_" . time() . "-" . Str::slug($request->cover->getClientOriginalName()) . "." . $request->cover->getClientOriginalExtension() : "";

            $data = [
                'title' => $request->title,
                'description' => $request->description,
                'file' => $fileName,
                'cover' => $coverName,
                'slug' => Str::slug($request->title),
                'category_book_id' => $request->category_id,
                // 'topic_id' => json_encode($request->topic_id),
                'user_id' => Auth::user()->id,
                'created_by' => Auth::user()->id,
                'posted_at' => Carbon::createFromFormat('d-m-Y', $request->posted_at)

            ];

            // Create Berita
            $add = Book::create($data);
            // Step 3: Get the array of topic IDs (you can change this based on the request)
            $topicIds = $request->topic_id; // Assume $request->topic_id is an array of topic IDs

            // Step 4: Attach the topics to the book using the sync method
            $add->topics()->sync($topicIds); // This will insert the relationships into the pivot table


            if ($add) {

                // Storage::disk(['public' => 'books'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder books
                Helper::saveImage('cover', $fileName, $request, $this->destinationImage);
                Helper::saveFile('file', $fileName, $request, $this->destinationFile);



                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Berita Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Berita gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'berita_title'     => 'required',
            'description'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'category_id'  => 'required',
            'posted_at'  => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {
            $category_id = Ctg_Book::find($request->category_id);
            // check
            if (!$category_id) {
                return $this->error("Not Found", "Category Berita dengan ID = ($request->category_id) tidak ditemukan!", 404);
            }
            // search
            $datas = Book::findOrFail($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan!", 404);
            }

            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }
            $datas['berita_title'] = $request->berita_title;
            $datas['description'] = $request->description;
            $datas['slug'] = Str::slug($request->berita_title);
            $datas['views'] = $datas->views;
            $datas['category_id'] = $request->category_id;
            $datas['event_id'] = $request->event_id;
            $datas['user_id'] = Auth::user()->id;
            $datas['edited_by'] = Auth::user()->id;
            $datas['posted_at'] = Carbon::createFromFormat('d-m-Y', $request->posted_at);

            if ($request->hasFile('image')) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                // Image name
                $fileName = 'books_' . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension();
                $datas['image'] = $fileName;

                // Image save in public folder
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
            } else {
                if ($request->delete_image) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                    $datas['image'] = null;
                }
                $datas['image'] = $datas->image;
            }

            // Step 5: Sync the topics if topic IDs are provided in the request
            if ($request->has('topic_id') && is_array($request->topic_id)) {
                $datas->topics()->sync($request->topic_id); // This will update the pivot table
            }


            // update datas
            if ($datas->save()) {
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Berita Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Berita gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Book::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            if ($data->delete()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Berita dengan ID = ($id) Berhasil dihapus!");
            }

            return $this->error("FAILED", "Berita dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {

            $data = Book::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "Berita dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Detach topics from the book (this will remove the related records from the pivot table)
                $data->topics()->detach();
                // Old image delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Berita dengan ID = ($id) Berhasil dihapus permanen!");
            }
            return $this->error("FAILED", "Berita dengan ID = ($id) Gagal dihapus permanen!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Book::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Berita Berhasil!");
            }
            return $this->error("FAILED", "Restore Berita Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Book::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Berita dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Berita dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    function addViews($id_berita)
    {
        $datas = Book::find($id_berita);
        $datas['views'] = $datas->views + 1;
        return $datas->save();
    }

    // function query($kondisi = "posted_at")
    // {
    //     return Book::latest($kondisi == "views" ? 'views' : 'posted_at')
    //         ->select(['books.*']);
    // }

    function queryGetCategory($id)
    {
        return Ctg_Book::find($id);
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

        // $category_id = [
        //     'id' => $item['category_id'],
        //     'name' => self::queryGetCategory($item['category_id'])->title_category,
        //     'slug' => self::queryGetCategory($item['category_id'])->slug,
        // ];
        // $item->category_id = $category_id;

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
