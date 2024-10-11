<?php

namespace App\Repositories\Ctg_Berkas;

use App\Repositories\Ctg_Berkas\Ctg_BerkasInterface as Ctg_BerkasInterface;
use App\Models\Ctg_Berkas;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Ctg_BerkasResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Ctg_BerkasRequest;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Models\Category;
use App\Models\Berkas;
use App\Models\Berkas_Dinsos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class Ctg_BerkasRepository implements Ctg_BerkasInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_berkas;
    protected $generalRedisKeys;

    public function __construct(Ctg_Berkas $ctg_berkas)
    {
        $this->generalRedisKeys = 'ctg_berkas_';
        $this->ctg_berkas = $ctg_berkas;
    }

    public function getCtg_Berkas($request)
    {
        $getParam = $request->paginate;

        if (!empty($getParam)) {
            if ($getParam == 'false' or $getParam == 'FALSE') {
                return self::getAllCtg_BerkasUnpaginate();
            } else {
                return self::getAllCtg_Berkas();
            }
        } else {
            return self::getAllCtg_Berkas();
        }

        // switch (true) {
        //     case $getParam !== null && $getParam !== '""' && $getParam !== "":
        //         return self::getAllCtg_BerkasUnpaginate();
        //     default:
        //         return self::getAllCtg_Berkas();
        // }
    }

    // getAll
    public function getAllCtg_Berkas()
    {
        try {

            $key = $this->generalRedisKeys . "All_" . request()->get('page', 1);
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Berkas from (CACHE)", $result);
            };

            $ctg_berkas = Ctg_Berkas::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_berkas) {
                $modifiedData = $ctg_berkas->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                Redis::set($key, json_encode($ctg_berkas));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("List keseluruhan Kategori Berkas", $ctg_berkas);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // Unpaginate
    public function getAllCtg_BerkasUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "All_Unpaginate";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kategori Berkas from (CACHE)", $result);
            };

            $ctg_berkas = Ctg_Berkas::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->get();

            if ($ctg_berkas->isNotEmpty()) {
                $modifiedData = $ctg_berkas->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                Redis::set($key, json_encode($modifiedData));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("List keseluruhan Kategori Berkas", $modifiedData);
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
                return $this->success("(CACHE): Detail Kategori Berkas dengan ID = ($id)", $result);
            }

            $ctg_berkas = Ctg_Berkas::find($id);
            if ($ctg_berkas) {
                $createdBy = User::select('id', 'name')->find($ctg_berkas->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_berkas->edited_by);

                $ctg_berkas->created_by = optional($createdBy)->only(['id', 'name']);
                $ctg_berkas->edited_by = optional($editedBy)->only(['id', 'name']);


                Redis::set($key . $id, json_encode($ctg_berkas));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Kategori Berkas dengan ID $id", $ctg_berkas);
            } else {
                return $this->error("Not Found", "Kategori Berkas dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtg_Berkas($request)
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
            $ctg_berkas = new Ctg_Berkas();
            $ctg_berkas->title_category = $request->title_category;
            $ctg_berkas->slug = Str::slug($request->title_category, '-');

            $user = Auth::user();
            $ctg_berkas->created_by = $user->id;
            $ctg_berkas->edited_by = $user->id;

            $create = $ctg_berkas->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kategori Berkas Berhasil ditambahkan!", $ctg_berkas);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // update
    public function updateCtg_Berkas($request, $id)
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
            $category = Ctg_Berkas::find($id);

            // check
            if (!$category) {
                return $this->error("Not Found", "Kategori Berkas dengan ID = ($id) tidak ditemukan!", 404);
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
                    return $this->success("Kategori Berkas Berhasil diperharui!", $category);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCtg_Berkas($id)
    {
        try {
            $Ctg_berkas = Berkas_Dinsos::where('ctg_berkas_id', $id)->exists();
            $Ctg_berkasJunk = Berkas_Dinsos::withTrashed()->where('ctg_berkas_id', $id)->exists();
            if ($Ctg_berkas || $Ctg_berkasJunk) {
                return $this->error("Failed", "Kategori Berkas dengan ID = ($id) digunakan di Berkas!", 400);
            }
            // search
            $category = Ctg_Berkas::find($id);
            if (!$category) {
                return $this->error("Not Found", "Kategori Berkas dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $category->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Berkas dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}
