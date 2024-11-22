<?php

namespace App\Repositories\Topic;

use App\Helpers\Helper;
use App\Models\Book;
use App\Repositories\Topic\TopicInterface;
use App\Models\Topic;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Models\News;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class TopicRepository implements TopicInterface
{

    // Response API HANDLER
    use API_response;

    protected $topic;
    //Cache for 1 hour (3600 seconds)
    private $expiredRedis = 3600;
    private $nameKeyRedis = 'Topic-';

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    // getAll
    public function getAll($request)
    {
        try {
            // Step 1: Get the limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = in_array($request->order, ['asc', 'desc']) ? $request->order : 'desc';

            // Retrieve other parameters from the request
            $getSearch = $request->search;
            $getRead = $request->read;
            $getById = $request->id;
            $page = $request->page;
            $paginate = $request->paginate;

            $params = http_build_query([
                'id' => $getById,
                'Paginate' => $paginate,
                'Order' => $order,
                'Limit' => $limit,
                'Page' => $page,
                'Read' => $getRead,
                'Search' => $getSearch,
            ], '', ',#');
            // Generate Redis cache key
            // $params = "#id=" . $getById . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Search=" . $getSearch . ",#Read=" . $getRead;
            $key = $this->nameKeyRedis . "All" . request()->get('page', 1) . "#params" . $params;

            // Step 3: Check if data exists in Redis cache
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Kategori Berita By {$params} from (CACHE)", $result);
            }

            // Step 4: Build query to retrieve categories
            $query = Topic::orderBy('title', $order);

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
            return $this->success("List Topic By {$params}", $datas);
        } catch (\Exception $e) {
            // Handle exceptions and return error message
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }


    // create
    public function create($request)
    {
        // Step 1: Validate the request data
        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required|string|max:255', // Added validation for string type and max length
            ]
        );

        // Step 2: Check if validation fails
        if ($validator->fails()) {
            return $this->error("Validation Failed", $validator->errors(), 400);
        }

        try {
            // Step 3: Prepare data for topic creation
            $data = [
                'title' => $request->title,
                'slug' => Str::slug($request->title), // Generate a slug from the title
                'created_by' => Auth::user()->id, // Store the ID of the user who created the topic
            ];

            // Step 4: Create the topic
            $topic = Topic::create($data);

            // Step 5: Check if topic creation was successful
            if ($topic) {
                // Clear relevant Redis cache
                Helper::deleteRedis($this->nameKeyRedis . '*');

                // Step 6: Return success response with the created topic data
                return $this->success("Topic Berhasil ditambahkan!", $topic);
            } else {
                return $this->error("FAILED", "Gagal menambahkan Topic.", 400);
            }
        } catch (\Exception $e) {
            // Step 7: Handle unexpected errors and return a server error response
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // update
    public function update($request, $id)
    {
        // Step 1: Validate the input
        $validator = Validator::make(
            $request->only('title'),
            [
                'title' => 'required|string|max:255', // Added string and max length validation
            ]
        );

        // Step 2: Check if validation fails
        if ($validator->fails()) {
            return $this->error("Validation Failed", $validator->errors(), 400);
        }

        try {
            // Step 3: Find the topic by ID
            $topic = Topic::find($id);

            // Step 4: Check if the topic exists
            if (!$topic) {
                return $this->error("Not Found", "Topic dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 5: Update the topic
            $topic->title = $request->title;
            $topic->slug = Str::slug($request->title);
            $topic->edited_by = Auth::user()->id;

            // Step 6: Save the updated topic
            if ($topic->save()) {
                // Step 7: Clear relevant Redis cache
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("Topic Berhasil diperbarui!", $topic);
            }

            // Step 8: Return error if update failed
            return $this->error("FAILED", "Gagal memperbarui Topic.", 400);
        } catch (\Exception $e) {
            // Step 9: Handle unexpected errors
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // delete
    public function delete($id)
    {
        try {
            // Step 1: Check if the topic is associated with any book (active or soft deleted)
            $topicBook = Book::whereHas('topics', function ($query) use ($id) {
                // Correct the column name 'topics.id' with 'book_topic.topic_id'
                $query->where('book_topic.topic_id', $id);
            })->exists();

            if ($topicBook) {
                return $this->error("Failed", "Topic dengan ID = ($id) terpakai di Buku!", 400);
            }

            // Step 2: Search for the topic
            $topic = Topic::find($id);

            // Step 3: Check if the topic exists
            if (!$topic) {
                return $this->error("Not Found", "Topic dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Step 4: Detach the topic from the books in the pivot table
            $topic->books()->detach(); // This will remove the associations in the pivot table

            // Step 5: Delete the topic
            $del = $topic->delete();

            // Step 6: Check if the deletion was successful
            if ($del) {
                // Step 7: Clear Redis cache after successful deletion
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("COMPLETED", "Topic dengan ID = ($id) Berhasil dihapus!");
            }

            // Step 8: Return error if deletion fails
            return $this->error("FAILED", "Topic dengan ID = ($id) gagal dihapus!", 400);
        } catch (\Exception $e) {
            // Step 9: Handle unexpected errors
            return $this->error("Internal Server Error" . $e->getMessage(), $e->getMessage(), 500);
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

        // $topic_id = [
        //     'id' => $item['topic_id'],
        //     'name' => self::queryGetCategory($item['topic_id'])->title,
        //     'slug' => self::queryGetCategory($item['topic_id'])->slug,
        // ];
        // $item->topic_id = $topic_id;

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
