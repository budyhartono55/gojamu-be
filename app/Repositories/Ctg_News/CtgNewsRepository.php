<?php

namespace App\Repositories\Ctg_News;

use App\Helpers\Helper;
use App\Repositories\Ctg_News\CtgNewsInterface;
use App\Models\Ctg_News;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Models\News;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;


class CtgNewsRepository implements CtgNewsInterface
{

    // Response API HANDLER
    use API_response;

    protected $category;
    //Cache for 1 hour (3600 seconds)
    private $expiredRedis = 3600;
    private $nameKeyRedis = 'Ctg_News-';

    public function __construct(Ctg_News $category)
    {
        $this->category = $category;
    }

    // getAll
    public function getAllCategories($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getSearch = $request->search;
            $getRead = $request->read;
            $getById = $request->id;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Search=" . $getSearch . ",#Read=" . $getRead;

            $key = $this->nameKeyRedis . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Kategori Berita By {$params} from (CACHE)", $result);
            }

            // if ($request->limit === "false") {
            //     $datas = Ctg_News::latest('created_at')->get();
            //     $category = $this->($datas, true, false);
            // } else {
            //     $datas = Ctg_News::latest('created_at')->paginate($limit);
            //     $category = Helper::queryModifyUserForDatas($datas, true);
            // }

            $query = Ctg_News::orderBy('title_category', $order);


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


            // if ($category) {
            //     if (!Auth::check()) {
            //         $hidden = ['id'];
            //         $category->makeHidden($hidden);
            //     }
            Redis::set($key, json_encode($datas));
            Redis::expire($key, $this->expiredRedis);

            return $this->success("List Kategori Berita By {$params}", $datas);

            // return $this->success("List keseluruhan Kategori Berita", $category);
            // };

            //=========================
            // NO-REDIS
            // $kategori = Ctg_News::paginate(3);
            // return $this->success(" List kesuluruhan kategori", $kategori);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }


    // create
    public function createCategory($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_category' => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {

            $data = [
                'title_category' => $request->title_category,
                'slug' => Str::slug($request->title_category),
                'created_by' => Auth::user()->id,

            ];
            // Create category Berita
            $add = Ctg_News::create($data);

            if ($add) {
                Helper::deleteRedis($this->nameKeyRedis . '*');
                return $this->success("Kategori Berita Berhasil ditambahkan!", $data);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // update
    public function updateCategory($request, $id)
    {
        $validator = Validator::make(
            $request->only('title_category'),
            [
                'title_category' => 'required',
            ],
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            // search
            $kategori = Ctg_News::find($id);

            // check
            if (!$kategori) {
                return $this->error("Not Found", "Kategori Berita dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                // approved
                $kategori['title_category'] = $request->title_category;
                $kategori['slug'] = Str::slug($request->title_category);
                $kategori['edited_by'] = Auth::user()->id;

                //save 
                $update = $kategori->save();
                if ($update) {
                    Helper::deleteRedis($this->nameKeyRedis . '*');
                    return $this->success("Kategori berita Berhasil diperharui!", $kategori);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCategory($id)
    {
        try {
            $beritaKatagori = News::where('category_id', $id)->exists();
            $beritaKatagoriTrash = News::withTrashed()->where('category_id', $id)->exists();
            if ($beritaKatagori or $beritaKatagoriTrash) {
                return $this->error("Failed", "Kategori Berita dengan ID = ($id) terpakai di Berita!", 400);
            }
            // search
            $kategori = Ctg_News::find($id);
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
