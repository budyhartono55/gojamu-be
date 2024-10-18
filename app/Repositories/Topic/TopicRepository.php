<?php

namespace App\Repositories\Topic;

use App\Helpers\Helper;
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
            $limit = Helper::limitDatas($request);

            if (($request->order != null) or ($request->order != "")) {
                $order = $request->order == "desc" ? "desc" : "asc";
            } else {
                $order = "desc";
            }
            $getSearch = $request->search;
            $getRead = $request->read;
            $getById = $request->id;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Search=" . $getSearch . ",#Read=" . $getRead;

            $key = $this->nameKeyRedis . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Topic By {$params} from (CACHE)", $result);
            }

            // if ($request->limit === "false") {
            //     $datas = Topic::latest('created_at')->get();
            //     $topic = $this->($datas, true, false);
            // } else {
            //     $datas = Topic::latest('created_at')->paginate($limit);
            //     $topic = Helper::queryModifyUserForDatas($datas, true);
            // }

            $query = Topic::orderBy('title', $order);


            if ($request->filled('id')) {
                $query->where('id', $getById);

                // return self::getById($getById);
            }



            if ($request->filled('search')) {
                $query->where('slug', 'LIKE', '%' . $getSearch . '%');
            }



            if ($request->filled('read')) {
                $query->where('slug', $getRead);
                // return self::read($getRead, $order, $limit);
            }

            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }
            $datas = Self::queryGetModify($result, $setPaginate, true);


            // if ($topic) {
            //     if (!Auth::check()) {
            //         $hidden = ['id'];
            //         $topic->makeHidden($hidden);
            //     }
            Redis::set($key, json_encode($datas));
            Redis::expire($key, $this->expiredRedis);

            return $this->success("List Topic By {$params}", $datas);

            // return $this->success("List keseluruhan Kategori Berita", $topic);
            // };

            //=========================
            // NO-REDIS
            // $kategori = Topic::paginate(3);
            // return $this->success(" List kesuluruhan kategori", $kategori);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }


    // create
    public function create($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {

            $data = [
                'title' => $request->title,
                'slug' => Str::slug($request->title),
                'created_by' => Auth::user()->id,

            ];
            // Create topic Berita
            $add = Topic::create($data);

            if ($add) {
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("Topic Berhasil ditambahkan!", $data);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // update
    public function update($request, $id)
    {
        $validator = Validator::make(
            $request->only('title'),
            [
                'title' => 'required',
            ],
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            // search
            $kategori = Topic::find($id);

            // check
            if (!$kategori) {
                return $this->error("Not Found", "Topic dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                // approved
                $kategori['title_topic'] = $request->title_topic;
                $kategori['slug'] = Str::slug($request->title_topic);
                $kategori['edited_by'] = Auth::user()->id;

                //save 
                $update = $kategori->save();
                if ($update) {
                    Helper::deleteRedis($this->nameKeyRedis . '*');
                    return $this->success("Topic Berhasil diperharui!", $kategori);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function delete($id)
    {
        try {
            $beritaTopic = News::where('topic_id', $id)->exists();
            $beritaTopicTrash = News::withTrashed()->where('topic_id', $id)->exists();
            if ($beritaTopic or $beritaTopicTrash) {
                return $this->error("Failed", "Topic dengan ID = ($id) terpakai di Berita!", 400);
            }

            // $MediaTopic = Media::where('topic_id', $id)->exists();
            // $MediaTopicTrash = Media::withTrashed()->where('topic_id', $id)->exists();
            // if ($MediaTopic or $MediaTopicTrash) {
            //     return $this->error("Failed", "Topic dengan ID = ($id) terpakai di Media!", 400);
            // }
            // search
            $kategori = Topic::find($id);
            if (!$kategori) {
                return $this->error("Not Found", "Kategori Berita dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $kategori->delete();
            if ($del) {
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("COMPLETED", "Kategori Berita dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
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
        //     'name' => self::queryGet($item['topic_id'])->title_topic,
        //     'slug' => self::queryGet($item['topic_id'])->slug,
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
