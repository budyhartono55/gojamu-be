<?php

namespace App\Repositories\Media;

use App\Repositories\Media\MediaInterface as MediaInterface;
use App\Models\Media;
use App\Models\User;
use App\Http\Resources\MediaResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\MediaRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\CtgMedia;
use App\Models\Wilayah\Kecamatan;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;

class MediaRepository implements MediaInterface
{

    protected $media;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Media $media)
    {
        $this->media = $media;
        $this->generalRedisKeys = "media_";
    }

    // getAll
    public function getMedias($request)
    {
        $limit = Helper::limitDatas($request);
        $getSlug = $request->slug;
        $getCategory = $request->ctg;
        $getKeyword =  $request->search;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllMediaByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                return self::getAllMediaByCategorySlug($getCategory, $limit);
            }
            // } elseif (!empty($getSlug)) {
            //     return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllMediaByKeyword($getKeyword, $limit);
        } else {
            return self::getAllMedias();
        }
    }

    public function getAllMedias()
    {
        try {

            $key = $this->generalRedisKeys . "public_All_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Konten/Media", $result);
            }

            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias'])
                ->latest('created_at')
                ->paginate(12);

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($media));
                return $this->success("List keseluruhan Konten/Media", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeywordInCtg($slug, $keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Konten/Media dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $category = CtgMedia::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias'])
                ->where('ctg_media_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%');
                    // ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            // if ($media->total() > 0) {
            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $media);
            }
            return $this->error("Not Found", "Konten/Media dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllMediaByCategorySlug($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("(CACHE): List Keseluruhan Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug).", $result);
            }
            $category = CtgMedia::where('slug', $slug)->first();
            if ($category) {
                $media = Media::with(['createdBy', 'editedBy', 'ctgMedias'])
                    ->where('ctg_media_id', $category->id)
                    ->latest('created_at')
                    ->paginate($limit);

                // if ($media->total() > 0) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeyword($keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Konten/Media dengan keyword = ($keyword).", $result);
            }

            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%');
                    // ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan keyword = ($keyword)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media dengan keyword = ($keyword) tidak ditemukan!", 404);
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
    //             return $this->success("(CACHE): Detail Konten/Media dengan slug = ($slug)", $result);
    //         }

    //         $slug = Str::slug($slug);
    //         $media = Media::where('slug', $slug)
    //             ->latest('created_at')
    //             ->first();

    //         if ($media) {
    //             $createdBy = User::select('name')->find($media->created_by);
    //             $editedBy = User::select('name')->find($media->edited_by);
    //             $ctgMedias = CtgMedia::select(['id', 'title_ctg', 'slug'])->find($media->ctg_media_id);

    //             $media->ctg_media_id = optional($ctgMedias)->only(['id', 'title_ctg', 'slug']);
    //             $media->created_by = optional($createdBy)->only(['name']);
    //             $media->edited_by = optional($editedBy)->only(['name']);

    //             $key = Auth::check() ? $key : $key;
    //             Redis::setex($key, 60, json_encode($media));
    //             return $this->success("Detail Konten/Media dengan slug = ($slug)", $media);
    //         } else {
    //             return $this->error("Not Found", "Konten/Media dengan slug = ($slug) tidak ditemukan!", 404);
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
                return $this->success("(CACHE): Detail Konten/Media dengan ID = ($id)", $result);
            }

            $media = Media::find($id);
            if ($media) {
                $createdBy = User::select('name')->find($media->created_by);
                $editedBy = User::select('name')->find($media->edited_by);
                $ctgMedia = CtgMedia::select('id', 'title_ctg', 'slug')->find($media->ctg_media_id);

                $media->created_by = optional($createdBy)->only(['name']);
                $media->edited_by = optional($editedBy)->only(['name']);
                $media->ctg_media_id = optional($ctgMedia)->only(['id', 'title_ctg', 'slug']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($media));
                return $this->success("Detail Konten/Media dengan ID = ($id)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createMedia($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_media' =>  'required',
                'ctg_media_id' =>  'required',
                'icon'          =>  'image|
                                    mimes:jpeg,png,jpg,gif|
                                    max:3072',
            ],
            [
                'title_media.required' => 'Mohon masukkan nama layanan!',
                'url.required' => 'URL tidak boleh Kosong!',
                'ctg_media_id.required' => 'Masukkan ketegori layanan!',
                'icon.image' => 'Pastikan file foto bertipe gambar',
                'icon.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg dan gif',
                'icon.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $media = new Media();
            $media->title_media = $request->title_media;
            $media->url = $request->url ?? '';

            $ctg_media_id = $request->ctg_media_id;
            $ctg = CtgMedia::where('id', $ctg_media_id)->first();
            if ($ctg) {
                $media->ctg_media_id = $ctg_media_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori Media dengan ID = ($ctg_media_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('icon')) {
                $destination = 'public/icons';
                $icon = $request->file('icon');
                $iconName = $media->slug . "-" . time() . "." . $icon->getClientOriginalExtension();

                $media->icon = $iconName;
                //storeOriginal
                $icon->storeAs($destination, $iconName);

                // compress to thumbnail 
                Helper::resizeIcon($icon, $iconName, $request);
            }

            $user = Auth::user();
            $media->created_by = $user->id;
            $media->edited_by = $user->id;

            $create = $media->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Media Berhasil ditambahkan!", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateMedia($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_media' =>  'required',
                'icon'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',

            ],
            [
                'title_media.required' => 'Mohon masukkan nama layanan!',
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
            $media = Media::find($id);

            // checkID
            if (!$media) {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($request->hasFile('icon')) {
                //checkImage
                if ($media->icon) {
                    Storage::delete('public/icons/' . $media->icon);
                    Storage::delete('public/thumbnails/t_icons/' . $media->icon);
                }
                $destination = 'public/icons';
                $icon = $request->file('icon');
                $label = Str::slug($request->title_media, '-');
                $imageName = $label . "-" . time() . "." . $icon->getClientOriginalExtension();

                $media->icon = $imageName;
                //storeOriginal
                $icon->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeIcon($icon, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/icons/' . $media->icon);
                    Storage::delete('public/thumbnails/t_icons/' . $media->icon);
                    $media->icon = null;
                }
                $media->icon = $media->icon;
            }

            // approved
            $media['title_media'] = $request->title_media ?? $media->title_media;
            $media['url'] = $request->url ?? $media->url;

            $ctg_media_id = $request->ctg_media_id;
            $ctg = CtgMedia::where('id', $ctg_media_id)->first();
            if ($ctg) {
                $media['ctg_media_id'] = $ctg_media_id ?? $media->ctg_media_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori media dengan ID = ($ctg_media_id) tidak ditemukan!", 404);
            }
            $media['created_by'] = $media->created_by;
            $media['edited_by'] = Auth::user()->id;

            //save
            $update = $media->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Media Berhasil diperbaharui!", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteMedia($id)
    {
        try {
            // search
            $media = Media::find($id);
            if (!$media) {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($media->icon) {
                Storage::delete('public/icons/' . $media->icon);
                Storage::delete('public/thumbnails/t_icons/' . $media->icon);
            }
            // approved
            $del = $media->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Konten/Media dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
