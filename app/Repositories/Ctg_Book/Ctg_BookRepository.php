<?php

namespace App\Repositories\Ctg_Book;

use App\Helpers\Helper;
use App\Repositories\Ctg_Book\Ctg_BookInterface;
use App\Models\Ctg_Book;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Models\Book;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class Ctg_BookRepository implements Ctg_BookInterface
{

    // Response API HANDLER
    use API_response;

    protected $category;
    //Cache for 1 hour (3600 seconds)
    private $expiredRedis = 3600;
    private $nameKeyRedis = 'Ctg_Book-';

    public function __construct(Ctg_Book $category)
    {
        $this->category = $category;
    }

    // getAll
    public function getAllCategories($request)
    {
        try {
            // Step 1: Retrieve parameters from the request
            $limit = Helper::limitDatas($request);
            $order = in_array($request->order, ['asc', 'desc']) ? $request->order : 'desc';
            $paginate = $request->paginate;
            $page = $request->page;
            $getSearch = $request->search;
            $getRead = $request->read;
            $getById = $request->id;

            // Step 2: Generate Redis cache key using http_build_query for cleaner parameter string
            $params = http_build_query([
                'id' => $getById,
                'Paginate' => $paginate,
                'Order' => $order,
                'Limit' => $limit,
                'Page' => $page,
                'Search' => $getSearch,
                'Read' => $getRead
            ], '', ',#');

            // Step 3: Generate Redis key
            $key = $this->nameKeyRedis . "All" . request()->get('page', 1)  . $page . "#params" . $params;

            // Step 3: Check if data exists in Redis cache
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Kategori Buku By {$params} from (CACHE)", $result);
            }

            // Step 4: Build query to retrieve categories
            $query = Ctg_Book::orderBy('title_category', $order);

            // Apply filters based on request parameters
            if ($request->filled('id')) {
                $query->where('id', $getById);
            }

            if ($request->filled('search')) {
                $query->where('slug', 'LIKE', '%' . $getSearch . '%');
            }

            if ($request->filled('read')) {
                $query->where('slug', $getRead);
            }

            // Step 5: Handle pagination logic
            if ($request->filled('paginate') && $paginate === "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }

            // Step 6: Modify data if necessary
            $datas = Self::queryGetModify($result, $setPaginate, true);

            // Step 7: Cache the result in Redis for later use
            Redis::set($key, json_encode($datas));
            Redis::expire($key, $this->expiredRedis);

            // Step 8: Return the success response
            return $this->success("List Kategori Buku By {$params}", $datas);
        } catch (\Exception $e) {
            // Handle exceptions and return error message
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }


    // create
    public function createCategory($request)
    {
        // Step 1: Validate the request data
        $validator = Validator::make(
            $request->all(),
            [
                'title_category' => 'required|string|max:255', // Added validation for string type and max length
            ]
        );

        // Step 2: Check if validation fails
        if ($validator->fails()) {
            return $this->error("Validation Failed", $validator->errors(), 400);
        }

        try {
            // Step 3: Prepare data for category creation
            $data = [
                'title_category' => $request->title_category,
                'slug' => Str::slug($request->title_category), // Generate a slug from the title
                'created_by' => Auth::user()->id, // Store the ID of the user who created the category
            ];

            // Step 4: Create the category
            $category = Ctg_Book::create($data);

            // Step 5: Check if category creation was successful
            if ($category) {
                // Clear relevant Redis cache
                Helper::deleteRedis($this->nameKeyRedis . '*');

                // Step 6: Return success response with the created category data
                return $this->success("Kategori Buku Berhasil ditambahkan!", $category);
            } else {
                return $this->error("FAILED", "Gagal menambahkan kategori berita.", 400);
            }
        } catch (\Exception $e) {
            // Step 7: Handle unexpected errors and return a server error response
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // update
    public function updateCategory($request, $id)
    {
        // Step 1: Validate the input
        $validator = Validator::make(
            $request->only('title_category'),
            [
                'title_category' => 'required|string|max:255', // Added string and max length validation
            ]
        );

        // Step 2: Check if validation fails
        if ($validator->fails()) {
            return $this->error("Validation Failed", $validator->errors(), 400);
        }

        try {
            // Step 3: Find the category by ID
            $kategori = Ctg_Book::find($id);

            // Step 4: Check if the category exists
            if (!$kategori) {
                return $this->error("Not Found", "Kategori Buku dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 5: Update the category
            $kategori->title_category = $request->title_category;
            $kategori->slug = Str::slug($request->title_category);
            $kategori->edited_by = Auth::user()->id;

            // Step 6: Save the updated category
            if ($kategori->save()) {
                // Step 7: Clear relevant Redis cache
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("Kategori berita Berhasil diperbarui!", $kategori);
            }

            // Step 8: Return error if update failed
            return $this->error("FAILED", "Gagal memperbarui kategori berita.", 400);
        } catch (\Exception $e) {
            // Step 9: Handle unexpected errors
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // delete
    public function deleteCategory($id)
    {
        try {
            // Step 1: Check if the category is used in any active or soft deleted news
            $bookKatagori = Book::where('ctg_book_id', $id)->exists();
            $bookKatagoriTrash = Book::withTrashed()->where('ctg_book_id', $id)->exists();

            if ($bookKatagori || $bookKatagoriTrash) {
                return $this->error("Failed", "Kategori Buku dengan ID = ($id) terpakai di Buku!", 400);
            }

            // Step 2: Search for the category
            $kategori = Ctg_Book::find($id);

            // Step 3: Check if the category exists
            if (!$kategori) {
                return $this->error("Not Found", "Kategori Buku dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 4: Delete the category
            $del = $kategori->delete();

            // Step 5: Check if the deletion was successful
            if ($del) {
                // Step 6: Clear Redis cache after successful deletion
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("COMPLETED", "Kategori Buku dengan ID = ($id) Berhasil dihapus!");
            }

            // Step 7: Return error if deletion fails
            return $this->error("FAILED", "Kategori Buku dengan ID = ($id) gagal dihapus!", 400);
        } catch (\Exception $e) {
            // Step 8: Handle unexpected errors
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    function queryGetModify($datas, $paginate, $manyResult = false)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');

                $modifiedData = array_map(function ($item) {
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
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

        unset($item->createdBy, $item->editedBy);
        return $item;
    }
}
