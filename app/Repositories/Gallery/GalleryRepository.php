<?php

namespace App\Repositories\Gallery;

use App\Repositories\Gallery\GalleryInterface as GalleryInterface;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Gallery;

class GalleryRepository implements GalleryInterface
{

    protected $gallery;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Gallery $gallery)
    {
        $this->gallery = $gallery;
        $this->generalRedisKeys = "gallery_";
    }


    public function getGalleries($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getType = $request->type;
        $getCtg = $request->album;


        // if (!empty($getType)) {
        //     return $getType == 'photo' || $getType == 'video'
        //         ? self::getAllMedia($getType, $limit, $getCtg)
        //         : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        if (!empty($getType)) {
            return in_array($getType, ['photo', 'video', 'streaming'])
                ? self::getAllMedia($getType, $limit, $getCtg)
                : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllGalleries($getCtg, $limit);
        }
    }

    public function getAllGalleries($slug, $limit)
    {
        try {
            $page = request()->get("page", 1);
            $key = $this->generalRedisKeys . "public_All_" . $page . '_limit#' . $limit . ($slug ? '_slug#' . $slug : '');
            $keyAuth = $this->generalRedisKeys . "auth_All_" . $page . '_limit#' . $limit . ($slug ? '_slug#' . $slug : '');
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Gallery dari (CACHE)", $result);
            }

            $galleryQuery = Gallery::with(['ctg_galleries', 'createdBy', 'editedBy'])
                ->latest('created_at');

            if ($slug) {
                $galleryQuery->whereHas('ctg_galleries', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            }
            $gallery = $galleryQuery->paginate($limit);

            if ($gallery) {
                $modifiedData = $gallery->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(["id", "name"]);
                    $item->edited_by = optional($item->editedBy)->only(["id", "name"]);
                    $item->ctg_gallery_id = optional($item->ctg_galleries)->only(['id', "title_category", "slug"]);

                    unset($item->createdBy, $item->editedBy, $item->ctg_galleries);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($gallery));

                return $this->success("List Keseluruhan Gallery", $modifiedData);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMedia($mediaType, $limit, $slug)
    {
        try {
            $page = request()->get("page", 1);
            $key = $this->generalRedisKeys . "public_All_" . $page . "_" . $mediaType . ($slug ? '_slug#' . $slug : '') . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_All_" . $page . "_" . $mediaType . ($slug ? '_slug#' . $slug : '') . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;

            // if (Redis::exists($key)) {
            //     $result = json_decode(Redis::get($key));
            //     $mediaTypeText = $mediaType === 'photo' ? 'Photo' : 'Video';
            //     return $this->success("List $mediaTypeText di Gallery dari (CACHE)", $result);
            // }
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                $mediaTypeText = '';

                if ($mediaType === 'photo') {
                    $mediaTypeText = 'Photo';
                } elseif ($mediaType === 'video') {
                    $mediaTypeText = 'Video';
                } elseif ($mediaType === 'streaming') {
                    $mediaTypeText = 'Streaming';
                }

                return $this->success("(CACHE): List $mediaTypeText di Gallery", $result);
            }

            $query = Gallery::with(['createdBy', 'editedBy'])
                ->latest('created_at');

            if ($slug) {
                $query->whereHas('ctg_galleries', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            }

            if ($mediaType === "photo") {
                $query->where('url', '');
            } elseif ($mediaType === "video") {
                $query->where('url', '!=', '');
            } elseif ($mediaType === "streaming") {
                $query->where('title_gallery', 'LIKE', '%(STREAMING)%');
            }

            $gallery = $query->paginate($limit);

            if ($gallery) {
                $modifiedData = $gallery->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(["id", "name"]);
                    $item->edited_by = optional($item->editedBy)->only(["id", "name"]);
                    $item->ctg_gallery_id = optional($item->ctg_galleries)->only(['id', "title_category", "slug"]);

                    unset($item->createdBy, $item->editedBy, $item->ctg_galleries);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($gallery));

                $mediaTypeText = $mediaType === 'photo' ? 'Photo' : 'Video';
                return $this->success("List $mediaTypeText di Gallery", $gallery);
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
                return $this->success("Detail Gallery dengan ID = ($id) from (CACHE)", $result);
            }

            $gallery = Gallery::find($id);
            if ($gallery) {
                $createdBy = User::select('id', 'name')->find($gallery->created_by);
                $editedBy = User::select('id', 'name')->find($gallery->edited_by);
                $gallery->created_by = optional($createdBy)->only(['id', 'name']);
                $gallery->edited_by = optional($editedBy)->only(['id', 'name']);

                Redis::set($key . $id, json_encode($gallery));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Gallery dengan ID $id", $gallery);
            } else {
                return $this->error("Not Found", "Gallery dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createGallery($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_gallery'   =>    'required',
                'image'           =>    'required|
                                        image|
                                        mimes:jpeg,png,jpg,gif,svg|
                                        max:3072',
            ],
            [
                'title_gallery.required' => 'Mohon isikan title_gallery',
                'image.required' => 'Image tidak boleh kosong!',
                'image.image' => 'Pastikan file Image bertipe gambar',
                'image.mimes' => 'Format Image yang diterima hanya jpeg, png, jpg, gif dan svg',
                'image.max' => 'File Image terlalu besar, usahakan dibawah 2MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $gallery = new Gallery();
            $gallery->title_gallery = $request->title_gallery;
            $gallery->description = $request->description;
            $gallery->url = $request->url;
            $user = Auth::user();
            $gallery->created_by = $user->id;
            $gallery->edited_by = $user->id;
            $ctg_id = $request->ctg_gallery_id;
            $ctg = Ctg_Gallery::where('id', $ctg_id)->first();
            if (!empty($ctg_id)) {
                if ($ctg) {
                    $gallery->ctg_gallery_id = $ctg_id;
                } else {
                    return $this->error("Tidak ditemukan!", " Acara dengan ID = ($ctg_id) tidak ditemukan!", 404);
                }
            } else {
                $gallery->ctg_gallery_id = null;
            }

            if ($request->hasFile('image')) {
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $image = $request->file('image');
                $imageName = time() . "." . $image->getClientOriginalExtension();

                $gallery->file_type = $image->getClientOriginalExtension();
                $gallery->image = $imageName;
                //storeOriginal
                $image->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($image, $imageName, $request);
            }

            // Simpan objek Gallery
            $create = $gallery->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Gallery Berhasil ditambahkan!", $gallery);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateGallery($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_gallery'   =>    'required',
                'image'           =>    'image|
                                        mimes:jpeg,png,jpg,gif,svg|
                                        max:3072',
            ],
            [
                'title_gallery.required' => 'Mohon isikan title_gallery',
                'image.image' => 'Pastikan file Image bertipe gambar',
                'image.mimes' => 'Format Image yang diterima hanya jpeg, png, jpg, gif dan svg',
                'image.max' => 'File Image terlalu besar, usahakan dibawah 2MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }
        try {
            // search
            $gallery = Gallery::find($id);

            // Check if the gallery exists
            if (!$gallery) {
                return $this->error("Not Found", "Gallery dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                if ($request->hasFile('image')) {
                    if ($gallery->image) {
                        Storage::delete('public/images/' . $gallery->image);
                        Storage::delete('public/thumbnails/t_images/' . $gallery->image);
                    }
                    $destination = 'public/images';
                    $t_destination = 'public/thumbnails/t_images';
                    $image = $request->file('image');
                    $imageName = time() . "." . $image->getClientOriginalExtension();

                    $gallery->image = $imageName;
                    $gallery->file_type = $image->getClientOriginalExtension();

                    //storeOriginal
                    $image->storeAs($destination, $imageName);

                    // compress to thumbnail 
                    Helper::resizeImage($image, $imageName, $request);
                }
                $gallery->image = $gallery->image;
            }

            // approved
            $gallery['title_gallery'] = $request->title_gallery;
            $gallery['description'] = $request->description;
            $gallery['url'] = $request->url;

            $oldCreatedBy = $gallery->created_by;
            $gallery['created_by'] = $oldCreatedBy;
            $gallery['edited_by'] = Auth::user()->id;
            $ctg_id = $request->ctg_gallery_id;
            $ctg = Ctg_Gallery::where('id', $ctg_id)->first();
            if (!empty($ctg_id)) {
                if ($ctg) {
                    $gallery['ctg_gallery_id'] = $ctg_id;
                } else {
                    return $this->error("Tidak ditemukan!", " Acara dengan ID = ($ctg_id) tidak ditemukan!", 404);
                }
            } else {
                $gallery['ctg_gallery_id'] = null;
            }

            $update = $gallery->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Gallery Berhasil diperbaharui!", $gallery);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteGallery($id)
    {
        try {
            // search
            $gallery = Gallery::find($id);
            // return dd($gallery);
            if (!$gallery) {
                return $this->error("Not Found", "Gallery dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($gallery->image) {
                Storage::delete('public/images/' . $gallery->image);
                Storage::delete('public/thumbnails/t_images/' . $gallery->image);
            }

            $del = $gallery->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Gallery dengan ID = ($id) Berhasil dihapus!");
            }

            // approved
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
