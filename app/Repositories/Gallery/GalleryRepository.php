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
use Illuminate\Support\Facades\DB;
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
        // $getType = $request->type;
        $getCtg = $request->ctg;


        // if (!empty($getType)) {
        //     return $getType == 'photo' || $getType == 'video'
        //         ? self::getAllMedia($getType, $limit, $getCtg)
        //         : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        // USED
        // if (!empty($getType)) {
        //     return in_array($getType, ['photo', 'video', 'streaming'])
        //         ? self::getAllMedia($getType, $limit, $getCtg)
        //         : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        // } else

        if (!empty($getId)) {
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
                return $this->success("(CACHE): List Keseluruhan Gallery", $result);
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
                    $item->ctg_gallery_id = optional($item->ctg_galleries)->only(['id', "title_ctg", "slug"]);

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

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "Auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Gallery dengan ID = ($id)", $result);
            }

            $gallery = Gallery::find($id);
            if ($gallery) {
                $createdBy = User::select('id', 'name')->find($gallery->created_by);
                $editedBy = User::select('id', 'name')->find($gallery->edited_by);
                $gallery->created_by = optional($createdBy)->only(['id', 'name']);
                $gallery->edited_by = optional($editedBy)->only(['id', 'name']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($gallery));

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
                                        mimes:jpeg,png,jpg|
                                        max:3072',
            ],
            [
                'title_gallery.required' => 'Mohon isikan title_gallery',
                'image.required' => 'Image tidak boleh kosong!',
                'image.image' => 'Pastikan file Image bertipe gambar',
                'image.mimes' => 'Format Image yang diterima hanya jpeg, png, dan jpg',
                'image.max' => 'File Image terlalu besar, usahakan dibawah 3MB',
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

            $user = Auth::user();
            $gallery->created_by = $user->id;
            $gallery->edited_by = $user->id;
            $ctg_id = $request->ctg_gallery_id;
            $ctg = Ctg_Gallery::where('id', $ctg_id)->first();
            if (!empty($ctg_id)) {
                if ($ctg) {
                    $gallery->ctg_gallery_id = $ctg_id;
                } else {
                    return $this->error("Tidak ditemukan!", " Kategori Gallery dengan ID = ($ctg_id) tidak ditemukan!", 404);
                }
            } else {
                $gallery->ctg_gallery_id = null;
            }

            if ($request->hasFile('image')) {
                $destination = 'public/images';
                $image = $request->file('image');
                $imageName = 'glr_' . time() . "." . $image->getClientOriginalExtension();

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
                                        mimes:jpeg,png,jpg|
                                        max:3072',
            ],
            [
                'title_gallery.required' => 'Mohon isikan title_gallery',
                'image.image' => 'Pastikan file Image bertipe gambar',
                'image.mimes' => 'Format Image yang diterima hanya jpeg, png, dan jpg',
                'image.max' => 'File Image terlalu besar, usahakan dibawah 3MB',
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
                    $image = $request->file('image');
                    $imageName = "glr_" . time() . "." . $image->getClientOriginalExtension();

                    $gallery->image = $imageName;

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
            DB::beginTransaction();
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
                DB::commit();
                return $this->success("COMPLETED", "Gallery dengan ID = ($id) Berhasil dihapus!");
            }

            // approved
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}