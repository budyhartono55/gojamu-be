<?php

namespace App\Repositories\Book;

use App\Helpers\AuthHelper;
use App\Helpers\LogHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Book;
use App\Models\Book;
use App\Models\Topic;
use App\Repositories\Book\BookInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;


class BookRepository implements BookInterface
{
    private $book;
    // 1 Minute redis expired
    private $expired = 360;
    private $generalRedisKeys = 'Book-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    private $destinationFile = "files";
    use API_response;

    public function __construct(Book $book)
    {
        $this->book = $book;
    }

    public function getBook($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getSearch = $request->search;
            $getByCategory = $request->category;
            $getByFilter = $request->filter;
            $getByTopics = $request->topics;
            $getByUser = $request->user_id;
            $getFavoriteByUser = $request->favorit_user_id;
            $getRead = $request->read;
            $getById = $request->id;
            $getTrash = $request->trash;
            $page = $request->page;
            $paginate = $request->paginate;
            // $clientIpAddress = $request->getClientIp();
            $params = http_build_query([
                'id' => $getById,
                'Paginate' => $paginate,
                'Order' => $order,
                'Limit' => $limit,
                'Page' => $page,
                'Read' => $getRead,
                'Search' => $getSearch,
                'Trash' => $getTrash,
                'Category' => $getByCategory,
                'Topics' => $getByTopics,
                'User' => $getByUser,
                'FavoriteUser' => $getFavoriteByUser,

            ], '', ',#');

            // $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Category=" . $getByCategory . ",#Topics=" . $getByTopics . ",#User=" . $getByUser  . ",#FavoriteUser=" . $getFavoriteByUser . ",#Read=" . $getRead . ",#Search=" . $getSearch;

            $user = Auth::user(); // Get the currently authenticated user
            $statusLogin = !Auth::check() ? "-public-" : $user->username;
            $key = $this->generalRedisKeys . "All" . $statusLogin . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Book By {$params} from (CACHE)", $result);
            }
            $sql = $query = Book::with([
                'topics' => function ($query) {
                    // Only include the favorite entry for the current user
                    $query->select('id', 'title', 'slug'); // Select only 'id' and 'name' from the users table;
                },
                'ctg_book' => function ($query) {
                    // Only include the favorite entry for the current user
                    $query->select('id', 'title_category', 'slug'); // Select only 'id' and 'name' from the users table;
                },
                'favoritedBy' => function ($query) use ($user) {
                    // Only include the favorite entry for the current user
                    $query->where('user_id',  Auth::check() ? $user->id : 1)->select('user_id', 'name');
                    //  // Select only 'id' and 'name' from the users table;
                }
            ])->withCount('favoritedBy') // This will return the count of users who favorited the book
                ->orderBy('posted_at', $order);
            // Step 6: Build the query
            if ($getTrash === "true") {
                $query = $sql->onlyTrashed();
            } else {
                $query = $sql;
            }


            // Step 4: Apply search filter
            if ($request->filled('search')) {
                $query->where('berita_title', 'LIKE', '%' . $getSearch . '%');
            }

            // Step 5: Apply category filter
            if ($request->filled('category')) {
                $query->whereHas('ctg_book', function ($queryCategory) use ($request) {
                    return $queryCategory->where('slug', Str::slug($request->category));
                });
            }

            // Step 5: Apply category filter
            if ($request->filled('topics')) {
                $query->whereHas('topics', function ($queryTopic) use ($request) {
                    return $queryTopic->where('slug', Str::slug($request->topics));
                });
            }

            // Step 5: Apply category filter
            if ($request->filled('favorite_user_id')) {
                $query->whereHas('favoritedBy', function ($queryFavorite) use ($request) {
                    return $queryFavorite->where('user_id', $request->favorite_user_id);
                });
            }

            // Step 6: Apply custom filter for sorting
            if ($request->filled('filter')) {
                $filter = $getByFilter == "top" ? 'views' : 'posted_at';
                $query->orderBy($filter, $order);
            }
            // Step 6: Apply custom filter for sorting
            if ($request->filled('user_id')) {

                $query->where('created_by', $getByUser);
            }
            // Step 7: Apply read filter and increment views if not already viewed
            if ($request->filled('read')) {
                $bookItem = $query->where('slug', $getRead)->first();


                if ($bookItem or !Auth::check()) {
                    if (!session()->has('viewed_book_' . $getRead)) {
                        // Increment the views count if the book hasn't been viewed before in this session
                        $bookItem->increment('views');
                        session()->put('viewed_book_' . $getRead, true);  // Mark the book as viewed
                    }
                }
            }


            // Step 8: Apply id filter and increment views if not already viewed
            if ($request->filled('id')) {
                $bookItem = $query->where('id', $getById)->first();

                if ($bookItem or !Auth::check()) {
                    if (!session()->has('viewed_book_' . $getRead)) {
                        // Increment the views count if the book hasn't been viewed before in this session
                        $bookItem->increment('views');
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

            return $this->success("List Book By {$params}", $datas);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!" . $e->getMessage(), $e->getMessage());
        }
    }


    public function save($request)
    {
        // Step 1: Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'file' => 'nullable|mimes:pdf|max:10240',
            'ctg_book_id' => 'required',
            'topics' => 'required',
            'posted_at' => 'required|date_format:d-m-Y',
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            // Step 2: Check if category exists
            $ctg_book = Ctg_Book::find($request->ctg_book_id);
            if (!$ctg_book) {
                return $this->error("Not Found", "Category Book dengan ID = ($request->ctg_book_id) tidak ditemukan!", 404);
            }


            // Step 1: Get the topic IDs from the request
            $topicIds = explode(',', $request->topics);

            // Step 2: Check if all the topic IDs exist in the database
            $existingTopics = Topic::whereIn('id', $topicIds)->pluck('id')->toArray();

            // Step 3: If the number of existing topics doesn't match the number of provided topic IDs, return an error
            if (count($existingTopics) !== count($topicIds)) {
                return $this->error("Not Found", "ada topic IDs salah atau tidak terdaftar.", 404);
            }

            // Step 3: Handle file upload for cover
            $fileNameCover = '';
            if ($request->hasFile('cover')) {
                $fileNameCover = "book_cover_" . time() . "-" . Str::slug($request->cover->getClientOriginalName()) . "." . $request->cover->getClientOriginalExtension();
                // Save the cover image to the specified directory
                Helper::saveImage('cover', $fileNameCover, $request, $this->destinationImage);
            }

            // Step 4: Handle file upload for document
            $fileNameFile = '';
            $formattedSize = '';
            if ($request->hasFile('file')) {
                $fileNameFile = "book_file_" . time() . "-" . Str::slug($request->file->getClientOriginalName()) . "." . $request->file->getClientOriginalExtension();
                // Save the file document to the specified directory
                Helper::saveFile('file', $fileNameFile, $request, $this->destinationFile);
                $fileSize = $request->file->getSize();
                // Format file size to be human-readable
                $formattedSize = $this->formatSize($fileSize);
            }

            // Step 5: Prepare data for book creation
            $data = [
                'title' => $request->title,
                'description' => $request->description,
                'cover' => $fileNameCover,
                'file' => $fileNameFile,
                'file_size' => $formattedSize,
                'file_link' => $request->file_link,
                'slug' => Str::slug($request->title),
                'ctg_book_id' => $request->ctg_book_id,
                'created_by' => Auth::user()->id,
                'posted_at' => Carbon::createFromFormat('d-m-Y', $request->posted_at)
            ];

            // Step 6: Create the book record
            $book = Book::create($data);

            // Step 7: Attach topics to the book
            // if ($request->has('topics') && is_array($request->topics)) {
            $topics = explode(',', $request->topics); // Already validated as an array
            $book->topics()->attach($topics); // Attach topics to the pivot table
            // }

            // Step 8: Check if the book was created successfully
            if ($book) {
                // Clear Redis cache after insertion
                Helper::deleteRedis($this->generalRedisKeys . "*");
                LogHelper::addToLog("Tambah Buku: " . $request->title, $request);

                // Return success response
                return $this->success("Book Berhasil ditambahkan!", $data);
            }
            LogHelper::addToLog("Gagal Tambah Buku: " . $request->title, $request);

            // Return failure response if creation fails
            return $this->error("FAILED", "Book gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // Handle any unexpected errors
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        // Step 1: Validate the request
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',  // Allow null description
            'cover' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'file' => 'nullable|mimes:pdf|max:10240',
            'ctg_book_id' => 'required',
            'topics' => 'required',
            'posted_at' => 'required|date_format:d-m-Y',
        ]);

        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {


            // Step 3: Find the book recordy by ID
            $book = Book::find($id);
            if (!$book) {
                return $this->error("Not Found", "Book dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Check if the authenticated user is the owner
            // $ownershipCheck = 
            AuthHelper::isOwnerData($book);

            // If not the owner, the function returns an error, otherwise proceeds
            // if ($ownershipCheck !== true) {
            //     return $ownershipCheck; // If the check failed, return the error
            // }
            // Step 2: Check if the category exists
            $ctg_book = Ctg_Book::find($request->ctg_book_id);
            if (!$ctg_book) {
                return $this->error("Not Found", "Category Book dengan ID = ($request->ctg_book_id) tidak ditemukan!", 404);
            }


            // Step 1: Get the topic IDs from the request
            $topicIds = explode(',', $request->topics);

            // Step 2: Check if all the topic IDs exist in the database
            $existingTopics = Topic::whereIn('id', $topicIds)->pluck('id')->toArray();

            // Step 3: If the number of existing topics doesn't match the number of provided topic IDs, return an error
            if (count($existingTopics) !== count($topicIds)) {
                return $this->error("Not Found", "ada topic ID salah atau tidak terdaftar.", 404);
            }

            // Step 4: Update book fields
            $book->title = $request->title ?: $book->title;
            $book->description = $request->description ?: $book->description;  // Default to empty string if null
            $book->slug = $request->title ? Str::slug($request->title) : $book->slug;
            $book->file_link = $request->file_link ?: $book->file_link;
            $book->ctg_book_id = $request->ctg_book_id ?: $book->ctg_book_id;
            $book->edited_by = Auth::user()->id;
            $book->posted_at = $request->posted_at ? Carbon::createFromFormat('d-m-Y', $request->posted_at) : $book->posted_at;

            // Step 5: Handle cover image upload or deletion
            if ($request->hasFile('cover')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $book->cover);
                $fileNameCover = 'book_cover_' . time() . '-' . Str::slug($request->cover->getClientOriginalName()) . '.' . $request->cover->getClientOriginalExtension();
                $book->cover = $fileNameCover;
                Helper::saveImage('cover', $fileNameCover, $request, $this->destinationImage);
            } elseif ($request->delete_cover) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $book->cover);
                $book->cover = null;
            }

            // Step 6: Handle file document upload or deletion
            if ($request->hasFile('file')) {
                Helper::deleteFile($this->destinationFile, $book->file);
                $fileNameFile = 'book_file_' . time() . '-' . Str::slug($request->file->getClientOriginalName()) . '.' . $request->file->getClientOriginalExtension();
                $book->file = $fileNameFile;
                Helper::saveFile('file', $fileNameFile, $request, $this->destinationFile);
                $book->file_size = $this->formatSize($request->file->getSize());
            } elseif ($request->delete_file) {
                Helper::deleteFile($this->destinationFile, $book->file);
                $book->file = null;
                $book->file_size = null;
            }

            // Step 7: Sync the topics if topic IDs are provided
            // if ($request->has('topics') && is_array($request->topics)) {

            $book->topics()->sync($topicIds); // This will update the pivot table
            // }

            // Step 8: Save the updated book record
            if ($book->save()) {
                // Step 9: Clear the Redis cache after saving
                Helper::deleteRedis($this->generalRedisKeys . "*");
                LogHelper::addToLog("Ubah Buku: " . $request->title, $request);

                return $this->success("Book Berhasil diperbarui!", $book);
            }
            LogHelper::addToLog("Gagal Ubah Buku: " . $request->title, $request);

            return $this->error("FAILED", "Book gagal diperbarui!", 400);
        } catch (\Exception $e) {
            // Step 10: Handle any unexpected errors
            return $this->error("Internal Server Error!", $e->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        try {

            // Step 1: Find the book by ID
            $data = Book::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Book dengan ID = ($id) tidak ditemukan!", 404);
            }
            AuthHelper::isOwnerData($data);


            // Step 2: Attempt to delete the book
            if ($data->delete()) {
                // Step 3: Clear related Redis cache after successful deletion
                Helper::deleteRedis($this->generalRedisKeys . "*");
                // Log successful deletion
                LogHelper::addToLog("Buku berhasil dihapus dengan ID: $id", $data, false);

                // Step 4: Return success response
                return $this->success("COMPLETED", "Book dengan ID = ($id) Berhasil dihapus!");
            }
            LogHelper::addToLog("Buku gagal dihapus dengan ID: $id", $data, false);

            // Step 5: If deletion fails, return an error response
            return $this->error("FAILED", "Book dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // Step 6: Log the error for debugging purposes
            Log::error("Error deleting book with ID = ($id): " . $e->getMessage(), ['exception' => $e]);

            // Step 7: Return generic error message for internal server error
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }


    public function deletePermanent($id)
    {
        try {
            // Step 1: Find the trashed book item by ID
            $data = Book::onlyTrashed()->find($id);

            // Step 2: If the record is not found, return a 404 error
            if (!$data) {
                return $this->error("Not Found", "Book dengan ID = ($id) tidak ditemukan dalam sampah!", 404);
            }

            AuthHelper::isOwnerData($data);



            // Step 3: Permanently delete the book item
            if ($data->forceDelete()) {
                // Detach topics from the book (this will remove the related records from the pivot table)
                $data->topics()->detach();
                // Step 4: Delete the associated image if available
                if ($data->cover) {
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->cover);
                }
                if ($data->file) {
                    Helper::deleteFile($this->destinationFile, $data->file);
                }


                // Step 5: Clear related Redis cache after successful deletion
                Helper::deleteRedis($this->generalRedisKeys . "*");
                LogHelper::addToLog("Buku berhasil dihapus permanen dengan ID: $id", $data, false);

                // Step 6: Return success response after deletion
                return $this->success("COMPLETED", "Book dengan ID = ($id) Berhasil dihapus permanen!");
            }
            LogHelper::addToLog("Buku gagal dihapus permanen dengan ID: $id", $data, false);

            // Step 7: If deletion fails, return an error response
            return $this->error("FAILED", "Book dengan ID = ($id) Gagal dihapus permanen!", 400);
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
            $data = Book::onlyTrashed();
            // return $data;

            // Check if there are any trashed records to restore
            if (!$data->exists()) {
                return $this->error("Not Found", "Tidak ada Book yang ada di sampah untuk dipulihkan!", 404);
            }

            AuthHelper::isOwnerData($data);


            // Restore all trashed records
            $restored = $data->restore();

            // Check if restore was successful
            if ($restored) {
                // Clear Redis cache after successful restore
                Helper::deleteRedis($this->generalRedisKeys . "*");
                LogHelper::addToLog("Buku berhasil direstore", $data, false);

                // Return success response
                return $this->success("COMPLETED", "Semua Book di sampah telah berhasil dipulihkan!");
            }

            // If restore failed, return an error response
            return $this->error("FAILED", "Restore Book gagal!", 400);
        } catch (Exception $e) {
            // Log error and return an internal server error response
            Log::error("Error restoring all trashed berita: " . $e->getMessage(), ['exception' => $e]);
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            // Step 1: Find the trashed book item by ID
            $data = Book::onlyTrashed()->where('id', $id)->first();

            // Step 2: If the record is not found, return a 404 error
            if (!$data) {
                return $this->error("Not Found", "Book dengan ID = ($id) tidak ditemukan dalam sampah!", 404);
            }
            AuthHelper::isOwnerData($data);

            // Step 3: Restore the trashed book item
            if ($data->restore()) {
                // Step 4: Clear related Redis cache after successful restore
                Helper::deleteRedis($this->generalRedisKeys . "*");
                LogHelper::addToLog("Buku berhasil direstore dengan ID: $id", $data, false);

                // Step 5: Return success response after restoration
                return $this->success("COMPLETED", "Book dengan ID = ($id) Berhasil dipulihkan!");
            }
            LogHelper::addToLog("Buku gagal direstore dengan ID: $id", $data, false);

            // Step 6: If restoration fails, return an error response
            return $this->error("FAILED", "Restore Book dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // Step 7: Log the error for debugging purposes
            Log::error("Error restoring berita with ID = ($id): " . $e->getMessage(), ['exception' => $e]);

            // Step 8: Return generic error message for internal server error
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    // function addViews($id_berita)
    // {
    //     $datas = Book::find($id_berita);
    //     $datas['views'] = $datas->views + 1;
    //     return $datas->save();
    // }

    // function query($kondisi = "posted_at")
    // {
    //     return Book::latest($kondisi == "views" ? 'views' : 'posted_at')
    //         ->select(['book.*']);
    // }

    public function markAsFavorite($bookId)
    {
        try {
            $user = auth()->user(); // Get the authenticated user

            // Check if the book is already marked as favorite
            if ($user->favoriteBooks()->where('book_id', $bookId)->exists()) {
                return response()->json(['message' => 'Buku sudah ditandai sebagai favorit.'], 400);
            }

            // Mark the book as favorite
            $user->favoriteBooks()->attach($bookId, ['marked_at' => now()]);
            Helper::deleteRedis($this->generalRedisKeys . "*");

            return $this->success("COMPLETED", "Buku berhasil ditandai sebagai favorit!");
        } catch (Exception $e) {
            // Log error and return an internal server error response
            Log::error("Error Tandai Buku sebagai Favorit: " . $e->getMessage(), ['exception' => $e]);
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function removeFavorite($bookId)
    {
        try {

            $user = auth()->user();

            // Check if the book is marked as favorite
            if (!$user->favoriteBooks()->where('book_id', $bookId)->exists()) {
                return response()->json(['message' => 'Buku tidak ditandai sebagai favorit.'], 400);
            }

            // Unmark the book as favorite
            $user->favoriteBooks()->detach($bookId);
            Helper::deleteRedis($this->generalRedisKeys . "*");
            return $this->success("COMPLETED", "Buku dihapus dari favorit!");
        } catch (Exception $e) {
            // Log error and return an internal server error response
            Log::error("Error Hapus Buku sebagai Favorit: " . $e->getMessage(), ['exception' => $e]);
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function getFavoriteBooks($request)
    {
        $user = auth()->user();
        $favorites = $user->favoriteBooks; // Fetch all favorite books

        return response()->json($favorites);
        // $user = auth()->user();

        // // Retrieve all books where the pivot column 'favorite' is true
        // $favoriteBooks = $user->favoriteBooks()->wherePivot('favorite', true)->get();

        // return response()->json($favoriteBooks);
    }


    function queryGetCategory($id)
    {
        return ctg_book::find($id);
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

        // $ctg_book_id = [
        //     'id' => $item['ctg_book_id'],
        //     'name' => self::queryGetCategory($item['ctg_book_id'])->title_category,
        //     'slug' => self::queryGetCategory($item['ctg_book_id'])->slug,
        // ];
        // $item->ctg_book_id = $ctg_book_id;

        // $user_id = [
        //     'name' => Helper::queryGetUser($item['user_id']),
        // ];
        // $item->user_id = $user_id;
        // $item->image = Helper::convertImageToBase64('images/', $item->image);
        // $item = Helper::queryGetUserModify($item);
        $item->created_by = optional($item->createdBy)->only(['id', 'name']);
        $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
        // $item->topic->makeHidden('pivot');

        unset($item->createdBy, $item->editedBy, $item->deleted_at);

        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }

    private function formatSize($bytes)
    {
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . " " . $sizes[$factor];
    }
}
