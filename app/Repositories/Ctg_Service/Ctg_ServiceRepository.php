<?php

namespace App\Repositories\Ctg_Service;

use App\Repositories\Ctg_Service\Ctg_ServiceInterface as Ctg_ServiceInterface;
use App\Models\Ctg_Service;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use Illuminate\Support\Facades\Storage;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Helpers\Helper;
use Illuminate\Support\Facades\DB;

class Ctg_ServiceRepository implements Ctg_ServiceInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_service;
    protected $generalRedisKeys;

    public function __construct(Ctg_Service $ctg_service)
    {
        $this->generalRedisKeys = 'ctg_service_';
        $this->ctg_service = $ctg_service;
    }

    public function getCtg_Service($request)
    {
        $getParam = $request->paginate;

        if (!empty($getParam)) {
            if ($getParam == 'false' or $getParam == "FALSE") {
                return self::getAllCtg_ServiceUnpaginate();
            } else {
                return self::getAllCtg_Service();
            }
        } else {
            return self::getAllCtg_Service();
        }
    }

    // getAll
    public function getAllCtg_Service()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_"  . request()->get('page', 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get('page', 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Service", $result);
            };

            $ctg_service = Ctg_Service::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_service) {
                $modifiedData = $ctg_service->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($ctg_service));

                return $this->success("List keseluruhan Kategori Service", $ctg_service);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // Unpaginate
    public function getAllCtg_ServiceUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_Unpaginate_";
            $keyAuth = $this->generalRedisKeys . "auth_All_Unpaginate_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Service)", $result);
            };

            $ctg_service = Ctg_Service::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->get();

            if ($ctg_service->isNotEmpty()) {
                $modifiedData = $ctg_service->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($modifiedData));
                return $this->success("List keseluruhan Kategori Service-unpaginate", $modifiedData);
            }
            return $this->success("List keseluruhan Kategori Service-unpaginate", $ctg_service);
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
                return $this->success("(CACHE): Detail Kategori Service dengan ID = ($id)", $result);
            }

            $ctg_service = Ctg_Service::find($id);
            if ($ctg_service) {
                $createdBy = User::select('id', 'name')->find($ctg_service->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_service->edited_by);

                $ctg_service->created_by = optional($createdBy)->only(['name']);
                $ctg_service->edited_by = optional($editedBy)->only(['name']);


                Redis::set($key . $id, json_encode($ctg_service));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Kategori Service dengan ID $id", $ctg_service);
            } else {
                return $this->error("Not Found", "Kategori Service dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtg_Service($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg'       => 'required',
                'icon'           =>    'image|
                                        mimes:jpeg,png,jpg,gif,svg|
                                        max:3072',

            ],
            [
                'title_ctg.required' => 'Mohon masukkan judul kategori!',
                'icon.image' => 'Pastikan file Ikon bertipe gambar',
                'icon.mimes' => 'Format Ikon yang diterima hanya jpeg, png, jpg, gif dan svg',
                'icon.max' => 'File Ikon terlalu besar, usahakan dibawah 2MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $ctg_service = new Ctg_Service();
            $slugExists = Ctg_Service::where('slug', Str::slug($request->title_ctg, '-'))->exists();
            if (!$slugExists) {
                $ctg_service->title_ctg = $request->title_ctg;
                $ctg_service->react_icon = $request->react_icon;
                $ctg_service->color = $request->color;
                $ctg_service->slug = Str::slug($request->title_ctg, '-');

                if ($request->hasFile('icon')) {
                    $destination = 'public/icons';
                    $t_destination = 'public/thumbnails/t_icons';
                    $image = $request->file('icon');
                    $imageName = $ctg_service->slug . '-' . time() . "." . $image->getClientOriginalExtension();

                    $ctg_service->icon = $imageName;
                    //storeOriginal
                    $image->storeAs($destination, $imageName);

                    // compress to thumbnail 
                    Helper::resizeIcon($image, $imageName, $request);
                }

                $user = Auth::user();
                $ctg_service->created_by = $user->id;
                $ctg_service->edited_by = $user->id;

                $create = $ctg_service->save();
                if ($create) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kategori Service Berhasil ditambahkan!", $ctg_service);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Judul kategori service yang anda masukkan telah terdaftar di sistem kami.", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateCtg_Service($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg'       => 'required',
            ],
            [
                'title_ctg.required' => 'Mohon masukkan judul kategori!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $ctg_service = Ctg_Service::find($id);
            if (!$ctg_service) {
                return $this->error("Tidak ditemukan!", "Kategori Service dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                if ($request->hasFile('icon')) {
                    if ($ctg_service->icon) {
                        Storage::delete('public/icons/' . $ctg_service->icon);
                        Storage::delete('public/thumbnails/t_icons/' . $ctg_service->icon);
                    }
                    $destination = 'public/icons';
                    $t_destination = 'public/thumbnails/t_icons';
                    $image = $request->file('icon');
                    $imageName = $ctg_service->slug . '-' . time() . "." . $image->getClientOriginalExtension();

                    $ctg_service->icon = $imageName;
                    $image->storeAs($destination, $imageName);
                    // compress to thumbnail 
                    Helper::resizeIcon($image, $imageName, $request);
                }
                $ctg_service->icon = $ctg_service->icon;
            }
            $ctg_service['title_ctg'] = $request->title_ctg;
            $ctg_service['react_icon'] = $request->react_icon;
            $ctg_service['color'] = $request->color;
            $ctg_service['slug'] = Str::slug($request->title_ctg, '-');

            //ttd
            $ctg_service['created_by'] = $ctg_service->created_by;
            $ctg_service['edited_by'] = Auth::user()->id;

            $update = $ctg_service->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Kategori Service Berhasil diperharui!", $ctg_service);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteCtg_Service($id)
    {
        try {
            $ctg_Service = Service::where('ctg_service_id', $id)->exists();
            if ($ctg_Service) {
                return $this->error("Failed", "Kategori Service dengan ID = ($id) digunakan di Service!", 400);
            }
            $ctg_service = Ctg_Service::find($id);
            if (!$ctg_service) {
                return $this->error("Not Found", "Kategori Service dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $drop = $ctg_service->delete();
            if ($drop) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Service dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
