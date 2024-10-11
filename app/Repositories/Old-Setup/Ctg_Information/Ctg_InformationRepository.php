<?php

namespace App\Repositories\Ctg_Information;

use App\Repositories\Ctg_Information\Ctg_InformationInterface as Ctg_InformationInterface;
use App\Models\Ctg_Information;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Ctg_InformationResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Ctg_InformationRequest;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Models\Category;
use App\Models\Information;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class Ctg_InformationRepository implements Ctg_InformationInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_information;
    protected $generalRedisKeys;

    public function __construct(Ctg_Information $ctg_information)
    {
        $this->generalRedisKeys = 'ctg_information_';
        $this->ctg_information = $ctg_information;
    }

    public function getCtg_Information($request)
    {
        $getParam = $request->paginate;

        if (!empty($getParam)) {
            if ($getParam == 'false') {
                return self::getAllCtg_InformationUnpaginate();
            } else {
                return self::getAllCtg_Information();
            }
        } else {
            return self::getAllCtg_Information();
        }

        // switch (true) {
        //     case $getParam !== null && $getParam !== '""' && $getParam !== "":
        //         return self::getAllCtg_InformationUnpaginate();
        //     default:
        //         return self::getAllCtg_Information();
        // }
    }

    // getAll
    public function getAllCtg_Information()
    {
        try {

            $key = $this->generalRedisKeys . "All_" . request()->get('page', 1);
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Informasi from (CACHE)", $result);
            };

            $ctg_information = Ctg_Information::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_information) {
                $modifiedData = $ctg_information->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                Redis::set($key, json_encode($ctg_information));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("List keseluruhan Kategori Informasi", $ctg_information);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // Unpaginate
    public function getAllCtg_InformationUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "All_Unpaginate";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Informasi from (CACHE)", $result);
            };

            $ctg_information = Ctg_Information::with(['createdBy', 'editedBy'])
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

                return $this->success("List keseluruhan Kategori Informasi", $modifiedData);
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
                return $this->success("(CACHE): Detail Kategori Informasi dengan ID = ($id)", $result);
            }

            $ctg_information = Ctg_Information::find($id);
            if ($ctg_information) {
                $createdBy = User::select('id', 'name')->find($ctg_information->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_information->edited_by);

                $ctg_information->created_by = optional($createdBy)->only(['id', 'name']);
                $ctg_information->edited_by = optional($editedBy)->only(['id', 'name']);


                Redis::set($key . $id, json_encode($ctg_information));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Kategori Informasi dengan ID $id", $ctg_information);
            } else {
                return $this->error("Not Found", "Kategori Informasi dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtg_Information($request)
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
            $ctg_information = new Ctg_Information();
            $ctg_information->title_category = $request->title_category;
            $ctg_information->slug = Str::slug($request->title_category, '-');

            $user = Auth::user();
            $ctg_information->created_by = $user->id;
            $ctg_information->edited_by = $user->id;

            $create = $ctg_information->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kategori Informasi Berhasil ditambahkan!", $ctg_information);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // update
    public function updateCtg_Information($request, $id)
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
            $category = Ctg_Information::find($id);

            // check
            if (!$category) {
                return $this->error("Not Found", "Kategori Informasi dengan ID = ($id) tidak ditemukan!", 404);
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
                    return $this->success("Kategori Informasi Berhasil diperharui!", $category);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCtg_Information($id)
    {
        try {
            $Ctg_information = Information::where('ctg_information_id', $id)->exists();
            $Ctg_informationJunk = Information::withTrashed()->where('ctg_information_id', $id)->exists();
            if ($Ctg_information || $Ctg_informationJunk) {
                return $this->error("Failed", "Kategori Informasi dengan ID = ($id) digunakan di Informasi!", 400);
            }
            // search
            $category = Ctg_Information::find($id);
            if (!$category) {
                return $this->error("Not Found", "Kategori Informasi dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $category->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Informasi dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
