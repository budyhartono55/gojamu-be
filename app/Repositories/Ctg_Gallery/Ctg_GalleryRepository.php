<?php

namespace App\Repositories\Ctg_Gallery;

use App\Repositories\Ctg_Gallery\Ctg_GalleryInterface as Ctg_GalleryInterface;
use App\Models\Ctg_Gallery;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Models\Gallery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class Ctg_GalleryRepository implements Ctg_GalleryInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_gallery;
    protected $generalRedisKeys;

    public function __construct(Ctg_Gallery $ctg_gallery)
    {
        $this->generalRedisKeys = 'ctg_gallery_';
        $this->ctg_gallery = $ctg_gallery;
    }

    public function getCtg_Gallery($request)
    {
        $getParam = $request->paginate;

        if (!empty($getParam)) {
            if ($getParam == 'false' or $getParam == "FALSE") {
                return self::getAllCtg_GalleryUnpaginate();
            } else {
                return self::getAllCtg_Gallery();
            }
        } else {
            return self::getAllCtg_Gallery();
        }
    }

    // getAll
    public function getAllCtg_Gallery()
    {
        try {

            $key = $this->generalRedisKeys . "public_All_"  . request()->get('page', 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get('page', 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Gallery", $result);
            };

            $ctg_gallery = Ctg_Gallery::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_gallery) {
                $modifiedData = $ctg_gallery->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($ctg_gallery));

                return $this->success("List keseluruhan Kategori Gallery", $ctg_gallery);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // Unpaginate
    public function getAllCtg_GalleryUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_Unpaginate_";
            $keyAuth = $this->generalRedisKeys . "auth_All_Unpaginate_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Gallery-unpaginate", $result);
            };

            $ctg_gallery = Ctg_Gallery::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->get();

            if ($ctg_gallery->isNotEmpty()) {
                $modifiedData = $ctg_gallery->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                Redis::set($key, json_encode($modifiedData));
                Redis::expire($key, 60); // Cache for 60 seconds
                return $this->success("List keseluruhan Kategori Gallery-unpaginate", $modifiedData);
            }
            return $this->success("List keseluruhan Kategori Gallery-unpaginate", $ctg_gallery);
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
                return $this->success("(CACHE): Detail Kategori Gallery dengan ID = ($id)", $result);
            }

            $ctg_gallery = Ctg_Gallery::find($id);
            if ($ctg_gallery) {
                $createdBy = User::select('id', 'name')->find($ctg_gallery->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_gallery->edited_by);

                $ctg_gallery->created_by = optional($createdBy)->only(['id', 'name']);
                $ctg_gallery->edited_by = optional($editedBy)->only(['id', 'name']);


                Redis::set($key . $id, json_encode($ctg_gallery));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Kategori Gallery dengan ID $id", $ctg_gallery);
            } else {
                return $this->error("Not Found", "Kategori Gallery dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtg_Gallery($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg' => 'required',
            ],
            [
                'title_ctg.required' => 'Uppss, Judul kategori tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $ctg_gallery = new Ctg_Gallery();
            $ctg_gallery->title_ctg = $request->title_ctg;
            $ctg_gallery->slug = Str::slug($request->title_ctg, '-');

            $user = Auth::user();
            $ctg_gallery->created_by = $user->id;
            $ctg_gallery->edited_by = $user->id;

            $create = $ctg_gallery->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kategori Gallery Berhasil ditambahkan!", $ctg_gallery);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateCtg_Gallery($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg' => 'required',
            ],
            [
                'title_ctg.required' => 'Uppss, title_ctg tidak boleh kosong!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            // search
            $category = Ctg_Gallery::find($id);

            // check
            if (!$category) {
                return $this->error("Not Found", "Kategori Gallery dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                // approved
                $category->title_ctg = $request->title_ctg;
                $category['slug'] = Str::slug($request->title_ctg, '-');

                $oldCreatedBy = $category->created_by;
                $category['created_by'] = $oldCreatedBy;
                $category['edited_by'] = Auth::user()->id;
                //save 
                $update = $category->save();
                if ($update) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kategori Gallery Berhasil diperharui!", $category);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCtg_Gallery($id)
    {
        try {
            $Ctg_Gallery = Gallery::where('ctg_gallery_id', $id)->exists();
            // $Ctg_GalleryJunk = Gallery::withTrashed()->where('ctg_gallery_id', $id)->exists();
            if ($Ctg_Gallery) {
                return $this->error("Failed", "Kategori Gallery dengan ID = ($id) digunakan di Gallery!", 400);
            }
            // search
            $category = Ctg_Gallery::find($id);
            if (!$category) {
                return $this->error("Not Found", "Kategori Gallery dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $category->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Gallery dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
