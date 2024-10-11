<?php

namespace App\Repositories\Base;

use App\Repositories\Base\BaseInterface as BaseInterface;
use App\Models\Base;
use App\Models\User;
use App\Http\Resources\BaseResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Base;
use App\Models\Entrant;
use App\Models\Event_Program;
use App\Models\Wilayah\Kecamatan;
use App\Models\Wilayah\Kabupaten;
use Illuminate\Validation\Rules\Exists;
use Intervention\Image\Facades\Image;

class BaseRepository implements BaseInterface
{

    protected $base;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Base $base)
    {
        $this->base = $base;
        $this->generalRedisKeys = "base";
    }

    // getAll
    public function getBases($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getEvent = $request->e_key;
        $getSlug = $request->slug;

        // if (!empty($getEvent)) {
        // return self::getAllEntrantsByEvent($getEvent);
        if (!empty($getSlug)) {
            return self::showBySlug($getSlug);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllBases($getEvent);
        }
    }

    public function getAllBases($event_id)
    {
        try {
            $key = empty($event_id) ? $this->generalRedisKeys . "public_All_" . request()->get("page", 1) : $this->generalRedisKeys . "public_All_" . $event_id . request()->get("page", 1);
            $keyAuth = empty($event_id) ? $this->generalRedisKeys . "auth_All_" . request()->get("page", 1) : $this->generalRedisKeys . "auth_All_" . $event_id . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            $message = empty($event_id) ? "List keseluruhan Hunian" : "List Keseluruhan Hunian berdasarkan event_id = $event_id";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): $message", $result);
            }

            if (empty($event_id)) {
                $base = Base::with(['kabupaten', 'createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->latest('created_at')
                    ->paginate(12);
            } else {
                $base = Event_Program::find($event_id);
                if (!$base) {
                    return $this->error("Acara tidak ditemukan!", "Acara dengan ID = ($event_id) tidak terdaftar pada database kami!", 404);
                }

                $base = Base::with(['kabupaten', 'createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->where('event_id', $event_id)
                    ->latest('created_at')
                    ->paginate(12);
            }

            if ($base->isNotEmpty()) {
                $modifiedData = $base->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->event_id = optional($item->events)->only(['id', 'title_event', 'slug']);
                    $item->asal_kab_id = optional($item->kabupaten)->only(['id', 'nama']);
                    $item->mem_quantity = $item->entrants_count;

                    unset($item->createdBy, $item->editedBy, $item->events, $item->kabupaten, $item->entrants_count);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($base));
                return $this->success("$message", $base);
            } else {
                return $this->error("$message Tidak ditemukan!", [], 404);
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
                return $this->success("(CACHE): Detail Hunian dengan slug = ($slug)", $result);
            }

            $slug = Str::slug($slug);
            $base = Base::with('entrants')->withCount('entrants')
                ->where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($base) {
                $createdBy = User::select('name')->find($base->created_by);
                $editedBy = User::select('name')->find($base->edited_by);
                $kabupaten = Kabupaten::select('id', 'nama')->find($base->asal_kab_id);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($base->event_id);

                $base->asal_kab_id = optional($kabupaten)->only(['id', 'nama']);
                $base->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $base->mem_quantity = $base->entrants_count;
                //entrants
                $base->entrants = $base->entrants;

                $base->created_by = optional($createdBy)->only(['name']);
                $base->edited_by = optional($editedBy)->only(['name']);

                unset($base->entrants_count);
                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($base));
                return $this->success("Detail Hunian dengan slug = ($slug)", $base);
            } else {
                return $this->error("Not Found", "Hunian dengan slug = ($slug) tidak ditemukan!", 404);
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
                return $this->success("(CACHE): Detail Hunian dengan ID = ($id)", $result);
            }

            $base = Base::with('entrants')->withCount('entrants')->find($id);
            if ($base) {
                $createdBy = User::select('name')->find($base->created_by);
                $editedBy = User::select('name')->find($base->edited_by);
                $kabupaten = Kabupaten::select('id', 'nama')->find($base->asal_kab_id);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($base->event_id);

                $base->asal_kab_id = optional($kabupaten)->only(['id', 'nama']);
                $base->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $base->mem_quantity = $base->entrants_count;
                //entrants
                $base->entrants = $base->entrants;

                $base->created_by = optional($createdBy)->only(['name']);
                $base->edited_by = optional($editedBy)->only(['name']);

                unset($base->entrants_count);
                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($base));
                return $this->success("Detail Hunian dengan ID = ($id)", $base);
            } else {
                return $this->error("Not Found", "Hunian dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }


    // create
    public function createBase($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_base' =>  'required',
                'location' =>  'required',
                'asal_kab_id' =>  'required',
                'event_id' =>  'required',
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'title_base.required' => 'Mohon masukkan nama hunian!',
                'location.required' => 'Lokasi tidak boleh Kosong!',
                'asal_kab_id.required' => 'Masukkan kabupaten asal!',
                'event_id.required' => 'Masukkan Acara!',
                'photo.image' => 'Pastikan file foto bertipe gambar',
                'photo.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'photo.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $base = new Base();
            $base->title_base = $request->title_base;
            $base->location = $request->location;
            $base->url_location = $request->url_location;
            // $base->mem_quantity = $request->mem_quantity;

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $base->event_id = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            }

            $asal_kab_id = $request->asal_kab_id;
            $kab = Kabupaten::where('id', $asal_kab_id)->first();
            if (!empty($asal_kab_id)) {
                if ($kab) {
                    $base['asal_kab_id'] = $asal_kab_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Kabupaten dengan ID = ($asal_kab_id) tidak ditemukan!", 404);
                }
            }
            $base->slug = Str::slug($request->title_base, '-');
            $checkBase = Base::where('slug', $base->slug)->exists();
            if ($checkBase) {
                return $this->error('Terjadi Kesalahan', 'Nama Hunian yang anda masukkan telah terdaftar pada database kami, mohon masukkan nama Hunian lain.', 400);
            }

            if ($request->hasFile('photo')) {
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $imageName = $base->slug . "-" . time() . "." . $photo->getClientOriginalExtension();

                $base->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
            }

            $user = Auth::user();
            $base->created_by = $user->id;
            $base->edited_by = $user->id;

            $create = $base->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Hunian Berhasil ditambahkan!", $base);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateBase($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_base' =>  'required',
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'title_base.required' => 'Mohon masukkan nama hunian!',
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
            $base = Base::find($id);

            // checkID
            if (!$base) {
                return $this->error("Not Found", "Hunian dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($request->hasFile('photo')) {
                //checkImage
                if ($base->photo) {
                    Storage::delete('public/images/' . $base->photo);
                    Storage::delete('public/thumbnails/t_images/' . $base->photo);
                }
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $base->slug = Str::slug($request->title_base, '-');
                $imageName = $base->slug . "-" . time() . "." . $photo->getClientOriginalExtension();

                $base->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/images/' . $base->photo);
                    Storage::delete('public/thumbnails/t_images/' . $base->photo);
                    $base->photo = null;
                }
                $base->photo = $base->photo;
            }

            // approved
            $base['title_base'] = $request->title_base ?? $base->title_base;
            $base['location'] = $request->location ?? $base->location;
            $base['url_location'] = $request->url_location ?? $base->url_location;
            // $base['mem_quantity'] = $request->mem_quantity ?? $base->mem_quantity;

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $base['event_id'] = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            } else {
                $base['event_id'] = $base->event_id;
            }
            $asal_kab_id = $request->asal_kab_id;
            $kab = Kabupaten::where('id', $asal_kab_id)->first();
            if (!empty($asal_kab_id)) {
                if ($kab) {
                    $base['asal_kab_id'] = $asal_kab_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Kabupaten dengan ID = ($asal_kab_id) tidak ditemukan!", 404);
                }
            } else {
                $base['asal_kab_id'] = $base->asal_kab_id;
            }

            $base['slug'] =  Str::slug($request->title_base, '-');

            $base['created_by'] = $base->created_by;
            $base['edited_by'] = Auth::user()->id;

            //save
            $update = $base->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Hunian Berhasil diperbaharui!", $base);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteBase($id)
    {
        try {

            $base = Entrant::where('base_id', $id)->exists();
            // $baseJunk = Entrant::withTrashed()->where('base_id', $id)->exists();
            if ($base) {
                return $this->error("Gagal!", "Base dengan ID = ($id) digunakan di Entrant!", 400);
            }
            // search
            $base = Base::find($id);
            if (!$base) {
                return $this->error("Not Found", "Hunian dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($base->photo) {
                Storage::delete('public/images/' . $base->photo);
                Storage::delete('public/thumbnails/t_images/' . $base->photo);
            }
            // approved
            $del = $base->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Hunian dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
