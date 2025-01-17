<?php

namespace App\Repositories\User;

use App\Helpers\Helper;
use App\Models\User;
use App\Repositories\User\UserInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class UserRepository implements UserInterface
{
    // 1 hour redis expired
    private $expired = 3600;
    private $keyRedis = "user-";
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    private $User;
    use API_response;

    public function __construct(User $User)
    {
        $this->User = $User;
    }


    public function getAll($request)
    {
        try {
            $limit = Helper::limitDatas($request);
            $keyOne = $this->keyRedis . "getAll" . request()->get('page', 1) .  "#limit" . $limit;
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data user from (CACHE)", $result);
            }
            $datas = User::latest()->paginate($limit);
            $data = Helper::queryModifyUserForDatas($datas, true);

            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data User", $data);

            // $data = User::all();
            // return $this->success(
            //     " List semua data User",
            //     $data
            // );
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function getAllTrash($request)
    {
        try {
            $limit = Helper::limitDatas($request);
            $keyOne = $this->keyRedis . "getAllTrash" . request()->get('page', 1) . $limit;
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data Trash user  from (CACHE)", $result);
            }
            $datas = User::onlyTrashed()->latest()->paginate($limit);
            $data = Helper::queryModifyUserForDatas($datas, true);

            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data Trash User", $data);

            // $data = User::all();
            // return $this->success(
            //     " List semua data User",
            //     $data
            // );
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    // findOne
    public function getById($id)
    {
        try {
            $keyOne = $this->keyRedis . "getById-" . Str::slug($id);
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("User dengan ID = ($id) from (CACHE)", $result);
            }

            $datas = User::find($id);


            if (!empty($datas)) {
                $data = Helper::queryModifyUserForDatas($datas);
                Redis::set($keyOne, json_encode($data));
                Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
                return $this->success("User Dengan ID = ($id)", $data);
            }
            return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);

            // $data = User::find($id);

            // // Check the user
            // if (!$data) return $this->error("User dengan ID = ($id) tidak ditemukan!", 404);

            // return $this->success("Detail User", $data);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'username'     => 'required|unique:users',
            'email'     => 'required|unique:users',
            'password'           => 'required',
            'confirm_password' => 'required|same:password',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $fileName = $request->hasFile('image') ? "user_" . time() . "." . $request->image->getClientOriginalExtension() : "";

            $data = [
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'image' => $fileName,
                'password' => bcrypt($request->password),
                'address' => $request->address,
                'contact' => $request->contact,
                'created_by' => Auth::user()->id

            ];
            // Create User
            $add = User::create($data);

            if ($add) {
                // Storage::disk(['public' => 'User'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder User
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("User Berhasil ditambahkan!", $data);
            }
            return $this->error("FAILED", "User Gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'email'     => 'email',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3000'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {

            // search
            $datas = User::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tak diditemukan!", 404);
            }
            $cekUser = User::where('username', $request->username)->exists();
            if ($cekUser and $request->username !== "") {
                return $this->error("Upps, Validation Failed!", "User sudah dipakai", 422);
            }

            $cekEmail = User::where('email', $request->email)->exists();
            if ($cekEmail and $request->email !== "") {
                return $this->error("Upps, Validation Failed!", "Email sudah dipakai", 422);
            }

            if (!Hash::check($request->password, $datas->password)) {
                return $this->error("Upps, Validation Failed!", "Password Anda Salah", 403);
            }

            $fileName = $request->hasFile('image') ? "user_" . time() . "." . $request->image->getClientOriginalExtension() : "";

            $datas['name'] = $request->name;
            $datas['username'] = $request->username == "" ? $datas->username : $request->username;
            $datas['email'] = $request->email == "" ? $datas->email : $request->email;
            $datas['address'] = $request->address;
            $datas['contact'] = $request->contact;
            $datas['edited_by'] = Auth::user()->id;;
            if ($request->hasFile('image')) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);
                $datas->image = $fileName;
                // Image save in public folder
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
            } else {
                if ($request->delete_image) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                    $datas['image'] = null;
                }
                $datas['image'] = $datas->image;
            }

            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("User Berhasil diperbaharui!", $datas);
            }

            return $this->error("FAILED", "User Gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    public function deleteSementara($id)
    {
        try {

            // search
            $data = User::find($id);
            if (!$data) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            if ($data->delete()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "User dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "User dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    public function deletePermanent($id)
    {
        try {

            $data = User::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "User dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "User dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = User::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Restore User Berhasil!");
            }
            return $this->error("FAILED", "Restore User Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = User::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Restore User dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore User dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function changePassword($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_password'           => 'required',
            'old_password'           => 'required',
            'confirm_password' => 'required|same:old_password',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {

            // search
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            if (Hash::check($request->old_password, $datas->password)) {

                $datas['password'] = bcrypt($request->new_password);
                $datas['updated_by'] = Auth::user()->id;

                // update datas
                if ($datas->save()) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Password Berhasil diperbaharui!", $datas);
                }

                return $this->error("FAILED", "Password Gagal diperbaharui!", 400);
            }
            return $this->error("FAILED", "Password Lama Salah", 422);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function resetPassword($id)
    {

        try {

            // search
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            $datas['password'] = bcrypt($datas->username);
            $datas['updated_by'] = Auth::user()->id;

            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Password Berhasil direset!", $datas);
            }

            return $this->error("FAILED", "Password Gagal direset!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
