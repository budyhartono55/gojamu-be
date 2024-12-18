<?php

namespace App\Repositories\CtgMedia;

use App\Repositories\CtgMedia\CtgMediaInterface as CtgMediaInterface;
use App\Models\CtgMedia;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use Illuminate\Support\Facades\Storage;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Helpers\Helper;
use Illuminate\Support\Facades\DB;

class CtgMediaRepository implements CtgMediaInterface
{

    // Response API HANDLER
    use API_response;

    protected $ctg_media;
    protected $generalRedisKeys;

    public function __construct(CtgMedia $ctg_media)
    {
        $this->generalRedisKeys = 'ctg_media_';
        $this->ctg_media = $ctg_media;
    }

    public function getCtgMedia($request)
    {
        $getParam = $request->paginate;

        if (!empty($getParam)) {
            if ($getParam == 'false' or $getParam == "FALSE") {
                return self::getAllCtgMediaUnpaginate();
            } else {
                return self::getAllCtgMedia();
            }
        } else {
            return self::getAllCtgMedia();
        }
    }

    // getAll
    public function getAllCtgMedia()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_"  . request()->get('page', 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get('page', 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Konten/Media", $result);
            };

            $ctg_media = CtgMedia::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($ctg_media) {
                $modifiedData = $ctg_media->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($ctg_media));

                return $this->success("List keseluruhan Kategori Konten/Media", $ctg_media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // Unpaginate
    public function getAllCtgMediaUnpaginate()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_Unpaginate_";
            $keyAuth = $this->generalRedisKeys . "auth_All_Unpaginate_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Kategori Konten/Media)", $result);
            };

            $ctg_media = CtgMedia::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->get();

            if ($ctg_media->isNotEmpty()) {
                $modifiedData = $ctg_media->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($modifiedData));
                return $this->success("List keseluruhan Kategori Konten/Media-unpaginate", $modifiedData);
            }
            return $this->success("List keseluruhan Kategori Konten/Media-unpaginate", $ctg_media);
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
                return $this->success("(CACHE): Detail Kategori Konten/Media dengan ID = ($id)", $result);
            }

            $ctg_media = CtgMedia::find($id);
            if ($ctg_media) {
                $createdBy = User::select('id', 'name')->find($ctg_media->created_by);
                $editedBy = User::select('id', 'name')->find($ctg_media->edited_by);

                $ctg_media->created_by = optional($createdBy)->only(['name']);
                $ctg_media->edited_by = optional($editedBy)->only(['name']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($ctg_media));

                return $this->success("Kategori Konten/Media dengan ID $id", $ctg_media);
            } else {
                return $this->error("Not Found", "Kategori Konten/Media dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createCtgMedia($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg'       => 'required',
                'icon'           =>    'image|
                                        mimes:jpeg,png,jpg,gif|
                                        max:3072',

            ],
            [
                'title_ctg.required' => 'Mohon masukkan judul kategori!',
                'icon.image' => 'Pastikan file Ikon bertipe gambar',
                'icon.mimes' => 'Format Ikon yang diterima hanya jpeg, png, jpg, gif',
                'icon.max' => 'File Ikon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $ctg_media = new CtgMedia();
            $slugExists = CtgMedia::where('slug', Str::slug($request->title_ctg, '-'))->exists();
            if (!$slugExists) {
                $ctg_media->title_ctg = $request->title_ctg;
                $ctg_media->slug = Str::slug($request->title_ctg, '-');

                if ($request->hasFile('icon')) {
                    $destination = 'public/icons';
                    $icon = $request->file('icon');
                    $iconName = $ctg_media->slug . '-' . time() . "." . $icon->getClientOriginalExtension();
                    $ctg_media->icon = $iconName;
                    //storeOriginal
                    $icon->storeAs($destination, $iconName);

                    // compress to thumbnail 
                    Helper::resizeIcon($icon, $iconName, $request);
                }

                $user = Auth::user();
                $ctg_media->created_by = $user->id;
                $ctg_media->edited_by = $user->id;

                $create = $ctg_media->save();
                if ($create) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kategori Konten/Media Berhasil ditambahkan!", $ctg_media);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Judul kategori service yang anda masukkan telah terdaftar di sistem kami.", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateCtgMedia($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_ctg'       => 'required',
                'icon'           =>    'image|
                                        mimes:jpeg,png,jpg,gif|
                                        max:3072',
            ],
            [
                'title_ctg.required' => 'Mohon masukkan judul kategori!',
                'icon.image' => 'Pastikan file Ikon bertipe gambar',
                'icon.mimes' => 'Format Ikon yang diterima hanya jpeg, png, jpg, gif',
                'icon.max' => 'File Ikon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $ctg_media = CtgMedia::find($id);
            if (!$ctg_media) {
                return $this->error("Tidak ditemukan!", "Kategori Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                $ctg_media['title_ctg'] = $request->title_ctg;
                $ctg_media['slug'] = Str::slug($request->title_ctg, '-');

                //ttd
                $ctg_media['created_by'] = $ctg_media->created_by;
                $ctg_media['edited_by'] = Auth::user()->id;

                $update = $ctg_media->save();
                if ($update) {
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Kategori Konten/Media Berhasil diperharui!", $ctg_media);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // delete
    public function deleteCtgMedia($id)
    {
        try {
            $ctgMedia = Media::where('ctg_media_id', $id)->exists();
            if ($ctgMedia) {
                return $this->error("Failed", "Kategori Konten/Media dengan ID = ($id) digunakan di Konten/Media!", 400);
            }
            $ctg_media = CtgMedia::find($id);
            if (!$ctg_media) {
                return $this->error("Not Found", "Kategori Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($ctg_media->icon) {
                Storage::delete('public/icons/' . $ctg_media->icon);
                Storage::delete('public/thumbnails/t_icons/' . $ctg_media->icon);
            }
            // approved
            $drop = $ctg_media->delete();
            if ($drop) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Success", "Kategori Konten/Media dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
