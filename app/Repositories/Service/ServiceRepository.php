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
        $getId = $request->id;
        $getSlug = $request->slug;
        $getCategory = $request->ctg_slg;
        $getKeyword =  $request->search;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllServiceByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                return self::getAllServiceByCategorySlug($getCategory, $limit);
            }
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } elseif (!empty($getSlug)) {
            return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllServiceByKeyword($getKeyword, $limit);
        } else {
            return self::getAllServices();
        }
    }
    // public function getGmapsServices($request)
    // {
    //     $limit = Helper::limitDatas($request);
    //     $getId = $request->id;
    //     $getSearch = $request->search;
    //     $nextPage = $request->page;

    //     if (!empty($getId)) {
    //         return self::getDetailsPlaceGoogleMaps($getId);
    //     } else {
    //         return self::getAllPlaceGmaps($getSearch, $nextPage);
    //     }
    // }

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

            $service = Service::with(['kecamatan', 'createdBy', 'editedBy', 'ctgServices'])
                ->latest('created_at')
                ->paginate(12);

            if ($service) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);
                    $item->district_id = optional($item->kecamatan)->only(['id', 'nama']);

                    if (isset($item->v_distance)) {
                        $item->v_distance = $item->v_distance / 1000;
                    }

                    unset($item->createdBy, $item->editedBy, $item->ctgServices, $item->kecamatan);
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
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Layanan dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $category = Ctg_Service::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $service = Service::with(['kecamatan', 'createdBy', 'editedBy', 'ctgServices'])
                ->where('ctg_service_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_service', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
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
                    $item->district_id = optional($item->kecamatan)->only(['id', 'nama']);
                    if (isset($item->v_distance)) {
                        $item->v_distance = $item->v_distance / 1000;
                    }

                    unset($item->createdBy, $item->editedBy, $item->ctgServices, $item->kecamatan);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($service));

                return $this->success("List Keseluruhan Layanan berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $service);
            }
            return $this->error("Not Found", "Layanan dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
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
                $service = Service::with(['kecamatan', 'createdBy', 'editedBy', 'ctgServices'])
                    ->where('ctg_service_id', $category->id)
                    ->latest('created_at')
                    ->paginate($limit);

                // if ($service->total() > 0) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);
                    $item->district_id = optional($item->kecamatan)->only(['id', 'nama']);
                    if (isset($item->v_distance)) {
                        $item->v_distance = $item->v_distance / 1000;
                    }

                    unset($item->createdBy, $item->editedBy, $item->ctgServices, $item->kecamatan);
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
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Layanan dengan keyword = ($keyword).", $result);
            }

            $service = Service::with(['kecamatan', 'createdBy', 'editedBy', 'ctgServices'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_service', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            if ($service) {
                $modifiedData = $service->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_service_id = optional($item->ctgServices)->only(['id', 'title_ctg', 'slug']);
                    $item->district_id = optional($item->kecamatan)->only(['id', 'nama']);
                    if (isset($item->v_distance)) {
                        $item->v_distance = $item->v_distance / 1000;
                    }

                    unset($item->createdBy, $item->editedBy, $item->ctgServices, $item->kecamatan);
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

    public function showBySlug($slug)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $slug;
            $keyAuth = $this->generalRedisKeys . "auth_" . $slug;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Layanan dengan slug = ($slug)", $result);
            }

            $slug = Str::slug($slug);
            $service = Service::where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($service) {
                $createdBy = User::select('name')->find($service->created_by);
                $editedBy = User::select('name')->find($service->edited_by);
                $ctgServices = Ctg_Service::select(['id', 'title_ctg', 'slug'])->find($service->ctg_service_id);
                $districtId = Kecamatan::select('id', 'nama')->find($service->district_id);

                $service->district_id = optional($districtId)->only(['id', 'nama']);
                $service->ctg_service_id = optional($ctgServices)->only(['id', 'title_ctg', 'slug']);
                $service->created_by = optional($createdBy)->only(['name']);
                $service->edited_by = optional($editedBy)->only(['name']);
                if (isset($service->v_distance)) {
                    $service->v_distance = $service->v_distance / 1000;
                }

                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($service));
                return $this->success("Detail Layanan dengan slug = ($slug)", $service);
            } else {
                return $this->error("Not Found", "Layanan dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $id;
            $keyAuth = $this->generalRedisKeys . "auth_" . $id;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Layanan dengan ID = ($id)", $result);
            }

            $service = Service::find($id);
            if ($service) {
                $createdBy = User::select('name')->find($service->created_by);
                $editedBy = User::select('name')->find($service->edited_by);
                $district = Kecamatan::select('id', 'nama')->find($service->district_id);
                $ctgService = Ctg_Service::select('id', 'title_ctg', 'slug')->find($service->ctg_service_id);

                $service->created_by = optional($createdBy)->only(['name']);
                $service->edited_by = optional($editedBy)->only(['name']);
                $service->district_id = optional($district)->only(['id', 'nama']);
                $service->ctg_service_id = optional($ctgService)->only(['id', 'title_ctg', 'slug']);
                if (isset($service->v_distance)) {
                    $service->v_distance = $service->v_distance / 1000;
                }

                $key = Auth::check() ? $key : $key;
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
                'url_location' =>  'required',
                'ctg_service_id' =>  'required',
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'title_service.required' => 'Mohon masukkan nama layanan!',
                'url_location.required' => 'URL lokasi tidak boleh Kosong!',
                'ctg_service_id.required' => 'Masukkan ketegori layanan!',
                'photo.image' => 'Pastikan file foto bertipe gambar',
                'photo.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'photo.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $service = new Service();
            $service->title_service = $request->title_service;
            $service->facility = $request->facility;
            $service->description = $request->description;
            $service->address = $request->address;
            $service->url_location = $request->url_location;
            $service->v_distance = $request->v_distance;
            $service->v_duration = $request->v_duration;
            $service->contact = $request->contact;
            $service->email = $request->email;
            $service->facebook = $request->facebook;
            $service->instagram = $request->instagram;
            $service->twitter = $request->twitter;
            $service->youtube = $request->youtube;
            $service->tiktok = $request->tiktok;
            $service->website = $request->website;

            // $placeName = urlencode($request->title_service);
            // $gLink = "https://www.google.com/maps/search/$placeName";
            // $service->url_location = $gLink;

            $service->district_id = $request->district_id;
            $service->slug = Str::slug($request->title_service, '-');

            $ctg_service_id = $request->ctg_service_id;
            $ctg = Ctg_Service::where('id', $ctg_service_id)->first();
            if ($ctg) {
                $service->ctg_service_id = $ctg_service_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori Service dengan ID = ($ctg_service_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('photo')) {
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $imageName = $service->slug . "-" . time() . "." . $photo->getClientOriginalExtension();

                $service->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
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
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',

            ],
            [
                'title_service.required' => 'Mohon masukkan nama layanan!',
                'photo.image' => 'Pastikan file foto bertipe gambar',
                'photo.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'photo.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
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
            if ($request->hasFile('photo')) {
                //checkImage
                if ($service->photo) {
                    Storage::delete('public/images/' . $service->photo);
                    Storage::delete('public/thumbnails/t_images/' . $service->photo);
                }
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $service->slug = Str::slug($request->title_service, '-');
                $imageName = $service->slug . "-" . time() . "." . $photo->getClientOriginalExtension();

                $service->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/images/' . $service->photo);
                    Storage::delete('public/thumbnails/t_images/' . $service->photo);
                    $service->photo = null;
                }
                $service->photo = $service->photo;
            }

            // approved
            $service['title_service'] = $request->title_service ?? $service->title_service;
            $service['facility'] = $request->facility ?? $service->facility;
            $service['description'] = $request->description ?? $service->description;
            $service['address'] = $request->address ?? $service->address;
            $service['url_location'] = $request->url_location ?? $service->url_location;
            $service['v_duration'] = $request->v_duration ?? $service->v_duration;
            $service['v_distance'] = $request->v_distance ?? $service->v_distance;
            $service['contact'] = $request->contact ?? $service->contact;
            $service['email'] = $request->email ?? $service->email;
            $service['facebook'] = $request->facebook ?? $service->facebook;
            $service['instagram'] = $request->instagram ?? $service->instagram;
            $service['twitter'] = $request->twitter ?? $service->twitter;
            $service['youtube'] = $request->youtube ?? $service->youtube;
            $service['tiktok'] = $request->tiktok ?? $service->tiktok;
            $service['website'] = $request->website ?? $service->website;

            $service['district_id'] = $request->district_id ?? $service->district_id;
            $service['slug'] =  Str::slug($request->title_service, '-');
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
            if ($service->photo) {
                Storage::delete('public/images/' . $service->photo);
                Storage::delete('public/thumbnails/t_images/' . $service->photo);
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


    //======================================== GOOOOOOOOOOOOOOOGLE
    // public function getAllPlaceGmaps($search, $page)
    // {
    //     try {
    //         $key = $this->generalRedisKeys . "public_All_" . $search . request()->get("page", 1);
    //         $keyAuth = $this->generalRedisKeys . "auth_All_" . $search . request()->get("page", 1);
    //         $key = Auth::check() ? $keyAuth : $key;
    //         if (Redis::exists($key)) {
    //             $result = json_decode(Redis::get($key));
    //             return $this->success("(CACHE):List $search di Kabupaten Sumbawa Barat", $result);
    //         }

    //         $apiKey = env('GOOGLE_MAPS_API_KEY');
    //         //Koordinat KSB
    //         $location = '-8.4937,117.4206';
    //         $radius = 50000;
    //         $query =  $search . ' Kabupaten Sumbawa Barat, Nusa Tenggara Barat';
    //         $url = empty($page) ? "https://maps.googleapis.com/maps/api/place/textsearch/json?query=$query&location=$location&radius=$radius&key=$apiKey&language=id" : "https://maps.googleapis.com/maps/api/place/textsearch/json?query=$query&location=$location&radius=$radius&pagetoken=$page&key=$apiKey&language=id";

    //         // $url = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=$query&location=$vanueLatitude,$vanueLongitude&key=$apiKey&language=id";
    //         $response = Http::get($url);
    //         if (!$response->successful()) {
    //             return $this->error("Terjadi Kesalahan", "Google Maps API bermasalah!", $response->status());
    //         }

    //         $data = $response->json();
    //         $bucket = [];
    //         $destinations = [];
    //         $nextPageToken = $data['next_page_token'] ?? null;
    //         //tujuan
    //         foreach ($data['results'] as $result) {
    //             // $destinations[] = $result['name'];
    //             $destinations[] = $result['geometry']['location']['lat'] . ',' . $result['geometry']['location']['lng'];
    //         }

    //         //vanue Alun-Alun Taliwang
    //         $vanueLocation = "-8.7477361,116.8539868";
    //         $distanceMatrixUrl = "https://maps.googleapis.com/maps/api/distancematrix/json?destinations=" . implode('|', $destinations) . "&origins=$vanueLocation" . "&key=$apiKey&language=id";
    //         // dd($distanceMatrixUrl);
    //         $distanceMatrixResponse = Http::get($distanceMatrixUrl);
    //         $distanceMatrixData = $distanceMatrixResponse->json();
    //         // dd($distanceMatrixData);
    //         foreach ($data['results'] as $key => $result) {
    //             $placeName = urlencode($result['name']);
    //             $gLink = "https://www.google.com/maps/search/?api=1&query=$placeName";
    //             $photoKey = isset($result['photos'][0]['photo_reference']) ? $this->generatePhotoUrl($result['photos'][0]['photo_reference']) : null;

    //             //waktu sama jarak
    //             $distance = isset($distanceMatrixData['rows'][0]['elements'][$key]['distance']['text']) ? $distanceMatrixData['rows'][0]['elements'][$key]['distance']['text'] : 'Jarak tidak tersedia';
    //             $duration = isset($distanceMatrixData['rows'][0]['elements'][$key]['duration']['text']) ? $distanceMatrixData['rows'][0]['elements'][$key]['duration']['text'] : 'Durasi tidak tersedia';

    //             $bucket[] = [
    //                 // 'next_page_token' => $data['next_page_token'] ?? null,
    //                 'place_id' => $result['place_id'],
    //                 'name' => $result['name'],
    //                 'address' => $result['formatted_address'] ?? null,
    //                 'photo_key' => $photoKey,
    //                 'types' => $result['types'],
    //                 'gmaps_url' => $gLink,
    //                 'rating' => $result['rating'] ?? null,
    //                 'total_reviews' => $result['user_ratings_total'] ?? null,
    //                 'distance_from_vanue' => $distance,
    //                 'duration_from_vanue' => $duration

    //             ];
    //         }
    //         $responseWithToken = [
    //             'data' => $bucket,
    //             'next_page_token' => $nextPageToken
    //         ];

    //         $key = Auth::check() ? $keyAuth : $key;
    //         Redis::setex($key, 60, json_encode($responseWithToken));
    //         return $this->success("List $search di Kabupaten Sumbawa Barat", $responseWithToken);
    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error", $e->getMessage(), 499);
    //     }
    // }

    // public function getDetailsPlaceGoogleMaps($place_id)
    // {
    //     try {
    //         $key = $this->generalRedisKeys . "public_All_" . $place_id . request()->get("page", 1);
    //         $keyAuth = $this->generalRedisKeys . "auth_All_" . $place_id . request()->get("page", 1);
    //         $key = Auth::check() ? $keyAuth : $key;
    //         if (Redis::exists($key)) {
    //             $result = json_decode(Redis::get($key));
    //             return $this->success("(CACHE):Details tempat berdasarkan place_id = $place_id di Kabupaten Sumbawa Barat", $result);
    //         }
    //         $apiKey = env('GOOGLE_MAPS_API_KEY');
    //         $url = "https://maps.googleapis.com/maps/api/place/details/json?placeid=$place_id&key=$apiKey&language=id";

    //         $response = Http::get($url);
    //         if (!$response->successful()) {
    //             return $this->error("Terjadi Kesalahan", "Details, Google Maps API bermasalah!", $response->status());
    //         }

    //         $data = $response->json();
    //         if (isset($data['result'])) {
    //             $result = $data['result'];
    //             $photoKeys = [];
    //             if (isset($result['photos']) && is_array($result['photos'])) {
    //                 $i = 1;
    //                 foreach ($result['photos'] as $photo) {
    //                     if (isset($photo['photo_reference'])) {
    //                         $photo_reference = $photo['photo_reference'];
    //                         $photoKey = $this->generatePhotoUrl($photo_reference);
    //                         $photoKeys["key$i"] = $photoKey;
    //                         $i++;
    //                     }
    //                 }
    //             }

    //             $destinations = $result['geometry']['location']['lat'] . ',' . $result['geometry']['location']['lng'];
    //             $vanueLocation = "-8.7477361,116.8539868";
    //             $distanceMatrixUrl = "https://maps.googleapis.com/maps/api/distancematrix/json?destinations=" . $destinations . "&origins=$vanueLocation" . "&key=$apiKey&language=id";

    //             $distanceMatrixResponse = Http::get($distanceMatrixUrl);
    //             $distanceMatrixData = $distanceMatrixResponse->json();
    //             $distance = isset($distanceMatrixData['rows'][0]['elements'][0]['distance']['text']) ? $distanceMatrixData['rows'][0]['elements'][0]['distance']['text'] : 'Jarak tidak tersedia';
    //             $duration = isset($distanceMatrixData['rows'][0]['elements'][0]['duration']['text']) ? $distanceMatrixData['rows'][0]['elements'][0]['duration']['text'] : 'Durasi tidak tersedia';

    //             $bucket = [
    //                 'place_id' => $result['place_id'],
    //                 'name' => $result['name'],
    //                 'formatted_address' => $result['formatted_address'] ?? null,
    //                 'photo_keys' => $photoKeys ?? null,
    //                 'url' => $result['url'] ?? null,
    //                 'current_opening_hours' => isset($result['opening_hours']) && isset($result['opening_hours']['open_now']) ? $result['opening_hours']['open_now'] : null,
    //                 'opening_hours' => $result['opening_hours'] ?? null,
    //                 'types' => $result['types'],
    //                 'reviews' => $result['reviews'] ?? null,
    //                 'rating' => $result['rating'] ?? null,
    //                 'user_ratings_total' => $result['user_ratings_total'] ?? null,
    //                 'distance_from_vanue' => $distance,
    //                 'duration_from_vanue' => $duration
    //             ];


    //             Redis::setex($key, 60, json_encode($bucket));
    //             return $this->success("Details tempat berdasarkan place_id = $place_id di Kabupaten Sumbawa Barat", $bucket);
    //         } else {
    //             return $this->error("Terjadi Kesalahan", "Data tidak ditemukan dalam respons API Google Maps.", 500);
    //         }
    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error", $e->getMessage(), 499);
    //     }
    // }
    // public function getImageGmapsServices($photo_reference)
    // {
    //     try {
    //         $key = $this->generalRedisKeys . "public_All_" . $photo_reference . request()->get("page", 1);
    //         $keyAuth = $this->generalRedisKeys . "auth_All_" . $photo_reference . request()->get("page", 1);
    //         $key = Auth::check() ? $keyAuth : $key;
    //         if (Redis::exists($key)) {
    //             $imageBinaryData = Redis::get($key);
    //             return response($imageBinaryData)->header('Content-Type', 'image/jpeg');
    //         }
    //         $apiKey = env('GOOGLE_MAPS_API_KEY');
    //         $url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=$photo_reference&key=$apiKey";

    //         $response = Http::get($url);
    //         if ($response->successful()) {
    //             $imageBinaryData = $response->body();

    //             Redis::setex($key, 60, $imageBinaryData);
    //             return response($imageBinaryData)->header('Content-Type', 'image/jpeg');
    //         } else {
    //             return $this->error("Terjadi Kesalahan", "Photo, Google Maps API bermasalah!", $response->status());
    //         }
    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error", $e->getMessage(), 499);
    //     }
    // }
    // public function generatePhotoUrl($photo_reference)
    // {
    //     $photoUrl = $photo_reference;
    //     return $photoUrl;
    // }
}
