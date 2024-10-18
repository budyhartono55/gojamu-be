<?php

namespace App\Repositories\Service;

use App\Repositories\Service\ServiceInterface as ServiceInterface;
use App\Models\Service;
use App\Models\User;
use App\Http\Resources\ServiceResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ServiceRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Service;
use App\Models\Wilayah\Kecamatan;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;

class ServiceRepository implements ServiceInterface
{

    protected $service;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Service $service)
    {
        $this->service = $service;
        $this->generalRedisKeys = "service_";
    }

    // getAll
    public function getServices($request)
    {
        $limit = Helper::limitDatas($request);
        $getSlug = $request->slug;
        $getCategory = $request->ctg;
        $getKeyword =  $request->search;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllServiceByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                return self::getAllServiceByCategorySlug($getCategory, $limit);
            }
        } elseif (!empty($getSlug)) {
            return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllServiceByKeyword($getKeyword, $limit);
        } else {
            return self::getAllServices();
        }
    }

    public function getAllServices()
    {
        try {

            $key = $this->generalRedisKeys . "public_All_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Layanan", $result);
            }

            $service = Service::with(['createdBy', 'editedBy', 'ctgServices'])
                ->latest('created_at')
                ->paginate(12);

            if ($service) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgServices);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($service));
                return $this->success("List keseluruhan Layanan", $service);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllServiceByKeywordInCtg($slug, $keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Layanan dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $category = Ctg_Service::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $service = Service::with(['createdBy', 'editedBy', 'ctgServices'])
                ->where('ctg_service_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_service', 'LIKE', '%' . $keyword . '%');
                    // ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            // if ($service->total() > 0) {
            if ($service) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgServices);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($service));

                return $this->success("List Keseluruhan Layanan berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $service);
            }
            return $this->error("Not Found", "Layanan dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllServiceByCategorySlug($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("(CACHE): List Keseluruhan Layanan berdasarkan Kategori Layanan dengan slug = ($slug).", $result);
            }
            $category = Ctg_Service::where('slug', $slug)->first();
            if ($category) {
                $service = Service::with(['createdBy', 'editedBy', 'ctgServices'])
                    ->where('ctg_service_id', $category->id)
                    ->latest('created_at')
                    ->paginate($limit);

                // if ($service->total() > 0) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgServices);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($service));

                return $this->success("List Keseluruhan Layanan berdasarkan Kategori Layanan dengan slug = ($slug)", $service);
            } else {
                return $this->error("Not Found", "Layanan berdasarkan Kategori Layanan dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllServiceByKeyword($keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Layanan dengan keyword = ($keyword).", $result);
            }

            $service = Service::with(['createdBy', 'editedBy', 'ctgServices'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_service', 'LIKE', '%' . $keyword . '%');
                    // ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            if ($service) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgServices);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($service));

                return $this->success("List Keseluruhan Layanan berdasarkan keyword = ($keyword)", $service);
            } else {
                return $this->error("Not Found", "Layanan dengan keyword = ($keyword) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // public function showBySlug($slug)
    // {
    //     try {
    //         $key = $this->generalRedisKeys . "public_" . $slug;
    //         $keyAuth = $this->generalRedisKeys . "auth_" . $slug;
    //         $key = Auth::check() ? $keyAuth : $key;
    //         if (Redis::exists($key)) {
    //             $result = json_decode(Redis::get($key));
    //             return $this->success("(CACHE): Detail Layanan dengan slug = ($slug)", $result);
    //         }

    //         $slug = Str::slug($slug);
    //         $service = Service::where('slug', $slug)
    //             ->latest('created_at')
    //             ->first();

    //         if ($service) {
    //             $createdBy = User::select('name')->find($service->created_by);
    //             $editedBy = User::select('name')->find($service->edited_by);
    //             $ctgServices = Ctg_Service::select(['id', 'title_ctg', 'slug'])->find($service->ctg_service_id);

    //             $service->ctg_service_id = optional($ctgServices)->only(['id', 'title_ctg', 'slug']);
    //             $service->created_by = optional($createdBy)->only(['name']);
    //             $service->edited_by = optional($editedBy)->only(['name']);

    //             $key = Auth::check() ? $key : $key;
    //             Redis::setex($key, 60, json_encode($service));
    //             return $this->success("Detail Layanan dengan slug = ($slug)", $service);
    //         } else {
    //             return $this->error("Not Found", "Layanan dengan slug = ($slug) tidak ditemukan!", 404);
    //         }
    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error", $e->getMessage(), 499);
    //     }
    // }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Layanan dengan ID = ($id)", $result);
            }

            $service = Service::find($id);
            if ($service) {
                $createdBy = User::select('name')->find($service->created_by);
                $editedBy = User::select('name')->find($service->edited_by);
                $ctgService = Ctg_Service::select('id', 'title_ctg', 'slug')->find($service->ctg_service_id);

                $service->created_by = optional($createdBy)->only(['name']);
                $service->edited_by = optional($editedBy)->only(['name']);
                $service->ctg_service_id = optional($ctgService)->only(['id', 'title_ctg', 'slug']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($service));
                return $this->success("Detail Layanan dengan ID = ($id)", $service);
            } else {
                return $this->error("Not Found", "Layanan dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createService($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_service' =>  'required',
                'ctg_service_id' =>  'required',
                'icon'          =>  'image|
                                    mimes:jpeg,png,jpg,gif|
                                    max:3072',
            ],
            [
                'title_service.required' => 'Mohon masukkan nama layanan!',
                'url.required' => 'URL tidak boleh Kosong!',
                'ctg_service_id.required' => 'Masukkan ketegori layanan!',
                'icon.image' => 'Pastikan file foto bertipe gambar',
                'icon.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg dan gif',
                'icon.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $service = new Service();
            $service->title_service = $request->title_service;
            $service->url = $request->url ?? '';

            $ctg_service_id = $request->ctg_service_id;
            $ctg = Ctg_Service::where('id', $ctg_service_id)->first();
            if ($ctg) {
                $service->ctg_service_id = $ctg_service_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori Service dengan ID = ($ctg_service_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('icon')) {
                $destination = 'public/icons';
                $icon = $request->file('icon');
                $iconName = $service->slug . "-" . time() . "." . $icon->getClientOriginalExtension();

                $service->icon = $iconName;
                //storeOriginal
                $icon->storeAs($destination, $iconName);

                // compress to thumbnail 
                Helper::resizeIcon($icon, $iconName, $request);
            }

            $user = Auth::user();
            $service->created_by = $user->id;
            $service->edited_by = $user->id;

            $create = $service->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Layanan Berhasil ditambahkan!", $service);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateService($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_service' =>  'required',
                'icon'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',

            ],
            [
                'title_service.required' => 'Mohon masukkan nama layanan!',
                'icon.image' => 'Pastikan file foto bertipe gambar',
                'icon.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'icon.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }
        try {
            // search
            $service = Service::find($id);

            // checkID
            if (!$service) {
                return $this->error("Not Found", "Layanan dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($request->hasFile('icon')) {
                //checkImage
                if ($service->icon) {
                    Storage::delete('public/icons/' . $service->icon);
                    Storage::delete('public/thumbnails/t_icons/' . $service->icon);
                }
                $destination = 'public/icons';
                $icon = $request->file('icon');
                $label = Str::slug($request->title_service, '-');
                $imageName = $label . "-" . time() . "." . $icon->getClientOriginalExtension();

                $service->icon = $imageName;
                //storeOriginal
                $icon->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeIcon($icon, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/icons/' . $service->icon);
                    Storage::delete('public/thumbnails/t_icons/' . $service->icon);
                    $service->icon = null;
                }
                $service->icon = $service->icon;
            }

            // approved
            $service['title_service'] = $request->title_service ?? $service->title_service;
            $service['url'] = $request->url ?? $service->url;

            $ctg_service_id = $request->ctg_service_id;
            $ctg = Ctg_Service::where('id', $ctg_service_id)->first();
            if ($ctg) {
                $service['ctg_service_id'] = $ctg_service_id ?? $service->ctg_service_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori service dengan ID = ($ctg_service_id) tidak ditemukan!", 404);
            }
            $service['created_by'] = $service->created_by;
            $service['edited_by'] = Auth::user()->id;

            //save
            $update = $service->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Layanan Berhasil diperbaharui!", $service);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteService($id)
    {
        try {
            // search
            $service = Service::find($id);
            if (!$service) {
                return $this->error("Not Found", "Layanan dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($service->icon) {
                Storage::delete('public/icons/' . $service->icon);
                Storage::delete('public/thumbnails/t_icons/' . $service->icon);
            }
            // approved
            $del = $service->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Layanan dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
