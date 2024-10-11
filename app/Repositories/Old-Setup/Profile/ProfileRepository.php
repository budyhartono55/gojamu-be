<?php

namespace App\Repositories\Profile;

use App\Helpers\Helper;
use App\Models\Profile;
use App\Repositories\Profile\ProfileInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class ProfileRepository implements ProfileInterface
{
    private $profile;
    // 1 hour redis expired
    private $expired = 3600;
    private $keyRedis = 'profile-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Profile $profile)
    {
        $this->profile = $profile;
    }


    public function getAll($request)
    {
        try {
            $limit = Helper::limitDatas($request);
            $nameLogin = !Auth::check() ? "-public-" : "-admin-";
            $keyOne = $this->keyRedis . "getAll" . $nameLogin . request()->get('page', 1) . "#limit" . $limit;
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data Profile from (CACHE)", $result);
            }
            $datas = Profile::first();
            $data = Helper::queryModifyUserForDatas($datas, false);
            if (!Auth::check() and $datas) {
                $hidden = ['id'];
                $data->makeHidden($hidden);
            }
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data Profile", $data);

            // $data = Profile::latest('created_at')->paginate(10);

            // return $this->success(
            //     " List semua data Profile",
            //     $data
            // );
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    // findOne
    public function getById($id)
    {
        try {
            $nameLogin = !Auth::check() ? "-public-" : "-admin-";
            $keyOne = $this->keyRedis . "getById-" . $nameLogin . Str::slug($id);
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("Profile dengan ID = ($id) from (CACHE)", $result);
            }

            $datas = Profile::find($id);
            if (!empty($datas)) {
                $data = Helper::queryModifyUserForDatas($datas);
                if (!Auth::check()) {
                    $hidden = ['id'];
                    $data->makeHidden($hidden);
                }
                Redis::set($keyOne, json_encode($data));
                Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
                return $this->success("Profile Dengan ID = ($id)", $data);
            }
            return $this->error("Not Found", "Profile dengan ID = ($id) tidak ditemukan!", 404);

            // $data = Profile::find($id);

            // // Check the data
            // if (!$data) return $this->error("Profile dengan ID = ($id) tidak ditemukan!", 404);

            // return $this->success("Detail Profile", $data);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'image_maklumat_pelayanan'  => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_tugas'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_struktur'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_about'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_profile_pimpinan'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_struktur_tpps'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072'

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $datas = Profile::first();
            if ($datas) {
                return $this->error("FAILED", "Profile sudah ada!", 400);
            }
            $image_maklumat_pelayanan = $request->hasFile('image_maklumat_pelayanan') ? 'image_maklumat_pelayanan_' . time() . "." . $request->image_maklumat_pelayanan->getClientOriginalExtension() : "";
            $image_tugas = $request->hasFile('image_tugas') ? 'image_tugas_' . time() . "." . $request->image_tugas->getClientOriginalExtension() : "";
            $image_struktur = $request->hasFile('image_struktur') ? 'image_struktur_' . time() . "." . $request->image_struktur->getClientOriginalExtension() : "";
            $image_about = $request->hasFile('image_about') ? 'image_about_' . time() . "." . $request->image_about->getClientOriginalExtension() : "";
            $image_profile_pimpinan = $request->hasFile('image_profile_pimpinan') ? 'image_profile_pimpinan_' . time() . "." . $request->image_profile_pimpinan->getClientOriginalExtension() : "";
            $image_struktur_tpps = $request->hasFile('image_struktur_tpps') ? 'image_struktur_tpps_' . time() . "." . $request->image_struktur_tpps->getClientOriginalExtension() : "";

            $data = [
                'about' => $request->about,
                'visi' => $request->visi,
                'misi' => $request->misi,
                'caption_vm' => $request->caption_vm,
                'maklumat_pelayanan' => $request->maklumat_pelayanan,
                'tugas_dan_fungsi' => $request->tugas_dan_fungsi,
                'sop_ppidkab' => $request->sop_ppidkab,
                'profil_pimpinan' => $request->profil_pimpinan,
                'image_maklumat_pelayanan' => $image_maklumat_pelayanan,
                'image_tugas' => $image_tugas,
                'image_struktur' => $image_struktur,
                'image_about' => $image_about,
                'image_profile_pimpinan' => $image_profile_pimpinan,
                'image_struktur_tpps' => $image_struktur_tpps,
                'created_by' => Auth::user()->id
            ];
            // Create Profile
            $add = Profile::create($data);

            if ($add) {
                // Storage::disk(['public' => 'profile'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder profile
                Helper::saveImage('image_maklumat_pelayanan', $image_maklumat_pelayanan, $request, $this->destinationImage);
                Helper::saveImage('image_tugas', $image_tugas, $request, $this->destinationImage);
                Helper::saveImage('image_struktur', $image_struktur, $request, $this->destinationImage);
                Helper::saveImage('image_about', $image_about, $request, $this->destinationImage);
                Helper::saveImage('image_profile_pimpinan', $image_profile_pimpinan, $request, $this->destinationImage);
                Helper::saveImage('image_struktur_tpps', $image_struktur_tpps, $request, $this->destinationImage);

                // if ($request->hasFile('image')) {
                //     $image = $request->file('image');
                //     $image->storeAs($this->destinationImage, $fileName, ['disk' => 'public']);
                //     Helper::resizeImage($image, $fileName, $request);
                // }
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Profile Berhasil ditambahkan!", $data);
            }
            return $this->error("FAILED", "Profile gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image_maklumat_pelayanan'  => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_tugas'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_struktur'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_about'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_profile_pimpinan'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'image_struktur_tpps'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {
            // search
            $datas = Profile::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Profile dengan ID = ($id) tidak ditemukan!", 404);
            }
            $image_maklumat_pelayanan = $request->hasFile('image_maklumat_pelayanan') ? 'image_maklumat_pelayanan_' . time() . "." . $request->image_maklumat_pelayanan->getClientOriginalExtension() : "";
            $image_tugas = $request->hasFile('image_tugas') ? 'image_tugas_' . time() . "." . $request->image_tugas->getClientOriginalExtension() : "";
            $image_struktur = $request->hasFile('image_struktur') ? 'image_struktur_' . time() . "." . $request->image_struktur->getClientOriginalExtension() : "";
            $image_about = $request->hasFile('image_about') ? 'image_about_' . time() . "." . $request->image_about->getClientOriginalExtension() : "";
            $image_profile_pimpinan = $request->hasFile('image_profile_pimpinan') ? 'image_profile_pimpinan_' . time() . "." . $request->image_profile_pimpinan->getClientOriginalExtension() : "";
            $image_struktur_tpps = $request->hasFile('image_struktur_tpps') ? 'image_struktur_tpps_' . time() . "." . $request->image_struktur_tpps->getClientOriginalExtension() : "";

            $datas['about'] = $request->about;
            $datas['visi'] = $request->visi;
            $datas['misi'] = $request->misi;
            $datas['caption_vm'] = $request->caption_vm;
            $datas['maklumat_pelayanan'] = $request->maklumat_pelayanan;
            $datas['tugas_dan_fungsi'] = $request->tugas_dan_fungsi;
            $datas['sop_ppidkab'] = $request->sop_ppidkab;
            $datas['profil_pimpinan'] = $request->profil_pimpinan;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('image_maklumat_pelayanan')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_maklumat_pelayanan);
                // public storage
                $datas['image_maklumat_pelayanan'] = $image_maklumat_pelayanan;
                Helper::saveImage('image_maklumat_pelayanan', $image_maklumat_pelayanan, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_maklumat_pelayanan) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_maklumat_pelayanan);

                    $datas['image_maklumat_pelayanan'] = "";
                }
                $datas['image_maklumat_pelayanan'] = $datas->image_maklumat_pelayanan;
            }
            if ($request->hasFile('image_tugas')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_tugas);
                // public storage
                $datas['image_tugas'] = $image_tugas;
                Helper::saveImage('image_tugas', $image_tugas, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_tugas) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_tugas);

                    $datas['image_tugas'] = "";
                }
                $datas['image_tugas'] = $datas->image_tugas;
            }

            if ($request->hasFile('image_struktur')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_struktur);
                // public storage
                $datas['image_struktur'] = $image_struktur;
                Helper::saveImage('image_struktur', $image_struktur, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_struktur) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_struktur);

                    $datas['image_struktur'] = "";
                }
                $datas['image_struktur'] = $datas->image_struktur;
            }
            if ($request->hasFile('image_about')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_about);
                // public storage
                $datas['image_about'] = $image_about;
                Helper::saveImage('image_about', $image_about, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_about) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_about);

                    $datas['image_about'] = "";
                }
                $datas['image_about'] = $datas->image_about;
            }
            if ($request->hasFile('image_profile_pimpinan')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_profile_pimpinan);
                // public storage
                $datas['image_profile_pimpinan'] = $image_profile_pimpinan;
                Helper::saveImage('image_profile_pimpinan', $image_profile_pimpinan, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_profile_pimpinan) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_profile_pimpinan);

                    $datas['image_profile_pimpinan'] = "";
                }
                $datas['image_profile_pimpinan'] = $datas->image_profile_pimpinan;
            }

            if ($request->hasFile('image_struktur_tpps')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_struktur_tpps);
                // public storage
                $datas['image_struktur_tpps'] = $image_struktur_tpps;
                Helper::saveImage('image_struktur_tpps', $image_struktur_tpps, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_struktur_tpps) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_struktur_tpps);

                    $datas['image_struktur_tpps'] = "";
                }
                $datas['image_struktur_tpps'] = $datas->image_struktur_tpps;
            }


            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Profile Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Profile Gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Profile::find($id);
            if (!$data) {
                return $this->error("Not Found", "Profile dengan ID = ($id) tidak ditemukan!", 404);
            }


            // approved
            if ($data->delete()) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_tugas);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_struktur);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_about);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_profile_pimpinan);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_struktur_tpps);

                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Profile dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "Profile dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    function query()
    {
        return Profile::join('users', 'users.id', '=', 'profile.created_by')
            ->join('users', 'users.id', '=', 'profile.edited_by')
            ->latest('created_at')
            ->select(['profile.*', 'users.name AS created_by', 'users.name AS edited_by']);
    }
}
