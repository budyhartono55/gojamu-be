<?php

namespace App\Repositories\Entrant;

use App\Repositories\Entrant\EntrantInterface as EntrantInterface;
use App\Models\Entrant;
use App\Models\User;
use App\Http\Resources\EntrantResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\EntrantRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Entrant;
use App\Models\Event_Program;
use App\Models\Base;
use App\Models\Contest;
use App\Models\Wilayah\Kecamatan;
use App\Models\Wilayah\Kabupaten;
use Intervention\Image\Facades\Image;

class EntrantRepository implements EntrantInterface
{

    protected $entrant;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Entrant $entrant)
    {
        $this->entrant = $entrant;
        $this->generalRedisKeys = "entrant_";
    }

    // getAll
    public function getEntrants($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getEvent = $request->e_key;

        if (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllEntrants($getEvent);
        }
    }

    public function getAllEntrants($event_id)
    {
        try {

            $key = empty($event_id) ? $this->generalRedisKeys . "public_All_" . request()->get("page", 1) : $this->generalRedisKeys . "public_All_" . $event_id . request()->get("page", 1);
            $keyAuth = empty($event_id) ? $this->generalRedisKeys . "auth_All_" . request()->get("page", 1) : $this->generalRedisKeys . "auth_All_" . $event_id . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            $message = empty($event_id) ? "List Keseluruhan Peserta" : "List Keseluruhan Peserta berdasarkan event_id = $event_id";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): $message", $result);
            }

            if (empty($event_id)) {
                $entrant = Entrant::with(['kabupaten', 'createdBy', 'editedBy', 'events', 'contests', 'bases'])
                    ->latest('created_at')
                    ->paginate(12);
            } else {
                $entrant = Event_Program::find($event_id);
                if (!$entrant) {
                    return $this->error("Acara tidak ditemukan!", "Acara dengan ID = ($event_id) tidak terdaftar pada database kami!", 404);
                }

                $entrant = Entrant::with(['kabupaten', 'createdBy', 'editedBy', 'events', 'contests', 'bases'])
                    ->where('event_id', $event_id)
                    ->latest('created_at')
                    ->paginate(12);
            }

            if ($entrant->isNotEmpty()) {
                $modifiedData = $entrant->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->event_id = optional($item->events)->only(['id', 'title_event', 'slug']);
                    $item->asal_kab_id = optional($item->kabupaten)->only(['id', 'nama']);
                    $item->contest_id = optional($item->contests)->only(['id', 'title_contest']);
                    $item->base_id = optional($item->bases)->only(['id', 'title_base']);

                    unset($item->createdBy, $item->editedBy, $item->events, $item->kabupaten, $item->contests, $item->bases);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($entrant));
                return $this->success("$message", $entrant);
            } else {
                return $this->error("$message Tidak ditemukan!", [], 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
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
                return $this->success("(CACHE): Detail Peserta dengan ID = ($id)", $result);
            }

            $entrant = Entrant::find($id);
            if ($entrant) {
                $createdBy = User::select('name')->find($entrant->created_by);
                $editedBy = User::select('name')->find($entrant->edited_by);
                $kabupaten = Kabupaten::select('id', 'nama')->find($entrant->asal_kab_id);
                $base = Base::select('id', 'title_base')->find($entrant->base_id);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($entrant->event_id);
                $contest = Contest::select('id', 'title_contest')->find($entrant->contest_id);

                $entrant->created_by = optional($createdBy)->only(['name']);
                $entrant->edited_by = optional($editedBy)->only(['name']);
                $entrant->asal_kab_id = optional($kabupaten)->only(['id', 'nama']);
                $entrant->base_id = optional($base)->only(['id', 'title_base']);
                $entrant->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $entrant->contest_id = optional($contest)->only(['id', 'title_contest']);

                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($entrant));
                return $this->success("Detail Peserta dengan ID = ($id)", $entrant);
            } else {
                return $this->error("Not Found", "Peserta dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createEntrant($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' =>  'required',
                'mem_evidence' =>  'required',
                'asal_kab_id' =>  'required',
                'event_id' =>  'required',
                'base_id' =>  'required',
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'name.required' => 'Mohon masukkan nama!',
                'asal_kab_id.required' => 'Masukkan kabupaten asal!',
                'event_id.required' => 'Masukkan Acara!',
                'base_id.required' => 'Mohon masukkan alamat hunian!',
                'photo.image' => 'Pastikan file foto bertipe gambar',
                'photo.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'photo.max' => 'File Icon terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $entrant = new Entrant();
            $entrant->name = $request->name;
            $entrant->mem_evidence = $request->mem_evidence;
            $entrant->contact = $request->contact;
            $entrant->gender = $request->gender;

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $entrant->event_id = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            }

            $asal_kab_id = $request->asal_kab_id;
            $kab = Kabupaten::where('id', $asal_kab_id)->first();
            if (!empty($asal_kab_id)) {
                if ($kab) {
                    $entrant['asal_kab_id'] = $asal_kab_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Kabupaten dengan ID = ($asal_kab_id) tidak ditemukan!", 404);
                }
            }

            $base_id = $request->base_id;
            $base = Base::where('id', $base_id)->first();
            if (!empty($base_id)) {
                if ($base) {
                    $entrant['base_id'] = $base_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Hunian dengan ID = ($base_id) tidak ditemukan!", 404);
                }
            }

            $contest_id = $request->contest_id;
            $contest = Contest::where('id', $contest_id)->first();
            if (!empty($contest_id)) {
                if ($contest) {
                    $entrant['contest_id'] = $contest_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Cabang Lomba dengan ID = ($contest_id) tidak ditemukan!", 404);
                }
            }

            if ($request->hasFile('photo')) {
                $destination = 'public/images/entrants';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $setKab = Str::slug($kab->nama, '-');
                $currentYear = date('Y');
                $imageName = $currentYear . '-' .  $setKab  . "-" . time() . "." . $photo->getClientOriginalExtension();

                $entrant->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
            }

            $user = Auth::user();
            $entrant->created_by = $user->id;
            $entrant->edited_by = $user->id;

            $create = $entrant->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Peserta Berhasil ditambahkan!", $entrant);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateEntrant($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' =>  'required',
                'photo'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'name.required' => 'Mohon masukkan nama!',
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
            $entrant = Entrant::find($id);

            // checkID
            if (!$entrant) {
                return $this->error("Not Found", "Peserta dengan ID = ($id) tidak ditemukan!", 404);
            }
            $asal_kab_id = $request->asal_kab_id;
            $kab = Kabupaten::where('id', $asal_kab_id)->first();
            if ($request->hasFile('photo')) {
                //checkImage
                if ($entrant->photo) {
                    Storage::delete('public/images/entrants/' . $entrant->photo);
                    Storage::delete('public/thumbnails/t_images/' . $entrant->photo);
                }
                $destination = 'public/images/entrants';
                $t_destination = 'public/thumbnails/t_images';
                $photo = $request->file('photo');
                $setKab = Str::slug($kab->nama, '-');
                $currentYear = date('Y');
                $imageName = $currentYear . '-' .  $setKab  . "-" . time() . "." . $photo->getClientOriginalExtension();

                $entrant->photo = $imageName;
                //storeOriginal
                $photo->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($photo, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/images/' . $entrant->photo);
                    Storage::delete('public/thumbnails/t_images/' . $entrant->photo);
                    $entrant->photo = null;
                }
                $entrant->photo = $entrant->photo;
            }

            // approved
            $entrant['name'] = $request->name ?? $entrant->name;
            $entrant['mem_evidence'] = $request->mem_evidence ?? $entrant->mem_evidence;
            $entrant['contact'] = $request->contact ?? $entrant->contact;
            $entrant['gender'] = $request->gender ?? $entrant->gender;

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $entrant['event_id'] = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            } else {
                $entrant['event_id'] = $entrant->event_id;
            }
            $asal_kab_id = $request->asal_kab_id;
            $kab = Kabupaten::where('id', $asal_kab_id)->first();
            if (!empty($asal_kab_id)) {
                if ($kab) {
                    $entrant['asal_kab_id'] = $asal_kab_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Kabupaten dengan ID = ($asal_kab_id) tidak ditemukan!", 404);
                }
            } else {
                $entrant['asal_kab_id'] = $entrant->asal_kab_id;
            }
            $base_id = $request->base_id;
            $base = Base::where('id', $base_id)->first();
            if (!empty($base_id)) {
                if ($base) {
                    $entrant['base_id'] = $base_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Hunian dengan ID = ($base_id) tidak ditemukan!", 404);
                }
            } else {
                $entrant['base_id'] = $entrant->base_id;
            }
            $contest_id = $request->contest_id;
            $contest = Contest::where('id', $contest_id)->first();
            if (!empty($contest_id)) {
                if ($contest) {
                    $entrant['contest_id'] = $contest_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Cabang Lomba dengan ID = ($contest_id) tidak ditemukan!", 404);
                }
            } else {
                $entrant['contest_id'] = $entrant->contest_id;
            }

            $entrant['created_by'] = $entrant->created_by;
            $entrant['edited_by'] = Auth::user()->id;

            //save
            $update = $entrant->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Peserta Berhasil diperbaharui!", $entrant);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // delete
    public function deleteEntrant($id)
    {
        try {
            // search
            $entrant = Entrant::find($id);
            if (!$entrant) {
                return $this->error("Not Found", "Peserta dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($entrant->photo) {
                Storage::delete('public/images/entrants/' . $entrant->photo);
                Storage::delete('public/thumbnails/t_images/' . $entrant->photo);
            }
            // approved
            $del = $entrant->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Peserta dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
