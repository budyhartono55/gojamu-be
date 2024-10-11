<?php

namespace App\Repositories\Ctg_Pemohon;

use App\Repositories\Ctg_Pemohon\Ctg_PemohonInterface as Ctg_PemohonInterface;
use App\Models\Ctg_Pemohon;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Ctg_PemohonResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Ctg_PemohonRequest;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Models\Category;
use App\Models\Pemohon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class Ctg_PemohonRepository implements Ctg_PemohonInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_pemohon;
    protected $generalRedisKeys;

    public function __construct(Ctg_Pemohon $ctg_pemohon)
    {
        $this->generalRedisKeys = 'ctg_pemohon_';
        $this->ctg_pemohon = $ctg_pemohon;
    }

    public function getCtg_Pemohon($request)
    {
        $getParam = $request->paginate;
        if (!empty($getParam)) {
            if ($getParam == 'false') {
                return self::getAllCtg_PemohonUnpaginate();
            } else {
                return self::getAllCtg_Pemohon();
            }
        } else {
            return self::getAllCtg_Pemohon();
        }
    }

    // getAll
    public function getAllCtg_Pemohon()
    {
        try {
            $key = $this->generalRedisKeys . "All_" . request()->get('page', 1);
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Pemohon from (CACHE)", $result);
            };

            $ctg_pemohon = Ctg_Pemohon::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_pemohon) {
                $modifiedData = $ctg_pemohon->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                // $ctg_pemohon['data'] = $modifiedData;

                Redis::set($key, json_encode($ctg_pemohon));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("List keseluruhan Kategori Pemohon", $ctg_pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // Unpaginate
    public function getAllCtg_PemohonUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "All_Unpaginate";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Pemohon from (CACHE)", $result);
            };

            $ctg_information = Ctg_Pemohon::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->get();

            if ($ctg_information->isNotEmpty()) {
                $modifiedData = $ctg_information->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                Redis::set($key, json_encode($modifiedData));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("List keseluruhan Kategori Pemohon", $modifiedData);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Kategori Pemohon dengan ID = ($id)", $result);
            }

            $ctg_pemohon = Ctg_Pemohon::find($id);
            if ($ctg_pemohon) {
                $createdBy = User::select('id', 'name')->find($ctg_pemohon->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_pemohon->edited_by);

                $ctg_pemohon->created_by = optional($createdBy)->only(['id', 'name']);
                $ctg_pemohon->edited_by = optional($editedBy)->only(['id', 'name']);

                Redis::set($key . $id, json_encode($ctg_pemohon));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Kategori Pemohon dengan ID $id", $ctg_pemohon);
            } else {
                return $this->error("Not Found", "Kategori Pemohon dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtg_Pemohon($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_category' => 'required',
            ],
            [
                'title_category.required' => 'Uppss, title_category tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $ctg_pemohon = new Ctg_Pemohon();
            $ctg_pemohon->title_category = $request->title_category;
            $ctg_pemohon->slug = Str::slug($request->title_category, '-');

            $user = Auth::user();
            $ctg_pemohon->created_by = $user->id;
            $ctg_pemohon->edited_by = $user->id;

            $create = $ctg_pemohon->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kategori Pemohon Berhasil ditambahkan!", $ctg_pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // update
    public function updateCtg_Pemohon($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_category' => 'required',
            ],
            [
                'title_category.required' => 'Uppss, title_category tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            // search
            $category = Ctg_Pemohon::find($id);

            // check
            if (!$category) {
                return $this->error("Not Found", "Kategori Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                // approved
                $category->title_category = $request->title_category;
                $category['slug'] = Str::slug($request->title_category, '-');

                $oldCreatedBy = $category->created_by;
                $category['created_by'] = $oldCreatedBy;
                $category['edited_by'] = Auth::user()->id;
                //save 
                $update = $category->save();
                if ($update) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kategori Pemohon Berhasil diperharui!", $category);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCtg_Pemohon($id)
    {
        try {
            $Ctg_pemohon = Pemohon::where('ctg_pemohon_id', $id)->exists();
            $Ctg_pemohonJunk = Pemohon::withTrashed()->where('ctg_pemohon_id', $id)->exists();
            if ($Ctg_pemohon || $Ctg_pemohonJunk) {
                return $this->error("Failed", "Kategori Pemohon dengan ID = ($id) digunakan di Pemohon!", 400);
            }
            // search
            $category = Ctg_Pemohon::find($id);
            if (!$category) {
                return $this->error("Not Found", "Kategori Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $category->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Pemohon dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
