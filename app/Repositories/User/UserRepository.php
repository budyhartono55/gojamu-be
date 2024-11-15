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
            $currentPage = request()->get('page', 1);
            $keyOne = "{$this->keyRedis}getAll#page{$currentPage}#limit{$limit}";

            // Periksa cache Redis
            $cachedData = Redis::get($keyOne);
            if ($cachedData) {
                $result = json_decode($cachedData);
                return $this->success("List Data User from CACHE", $result);
            }

            // Ambil data dari database
            $datas = User::latest()->paginate($limit);
            $data = Helper::queryModifyUserForDatas($datas, true);

            // Simpan data ke Redis
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache sesuai dengan waktu kedaluwarsa

            return $this->success("List Data User", $data);
        } catch (\Throwable $e) { // Gunakan \Throwable untuk menangkap semua jenis error
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    public function getAllTrash($request)
    {
        try {
            $limit = Helper::limitDatas($request);
            $currentPage = request()->get('page', 1);
            $keyOne = "{$this->keyRedis}getAllTrash#page{$currentPage}#limit{$limit}";

            // Periksa cache Redis
            $cachedData = Redis::get($keyOne);
            if ($cachedData) {
                $result = json_decode($cachedData);
                return $this->success("List Data Trash User from CACHE", $result);
            }

            // Ambil data dari database (hanya yang soft deleted)
            $datas = User::onlyTrashed()->latest()->paginate($limit);
            $data = Helper::queryModifyUserForDatas($datas, true);

            // Simpan data ke Redis dengan waktu kedaluwarsa
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired);

            return $this->success("List Data Trash User", $data);
        } catch (\Throwable $e) { // Tangkap semua jenis error
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    // findOne
    public function getById($id)
    {
        try {
            // Pastikan hanya Admin atau pemilik akun yang dapat mengakses
            $currentUser = auth()->user();
            if ($currentUser->id !== $id && $currentUser->role !== "Admin") {
                return $this->error("Unauthorized", "Anda tidak memiliki hak untuk melihat data ini!", 403);
            }

            // Key Redis untuk cache
            $keyOne = "{$this->keyRedis}getById-" . Str::slug($id);

            // Periksa cache Redis
            $cachedData = Redis::get($keyOne);
            if ($cachedData) {
                $result = json_decode($cachedData);
                return $this->success("User dengan ID = ($id) from CACHE", $result);
            }

            // Ambil data dari database
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Modifikasi data dan simpan ke Redis
            $data = Helper::queryModifyUserForDatas($datas);
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired);

            return $this->success("User dengan ID = ($id)", $data);
        } catch (\Throwable $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }

    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'username'     => 'required|unique:users',
            'email'     => 'required|unique:users',
            'password'           => 'required',
            'jenis_kelamin'           => 'required',
            'active'           => 'required',
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
                'jenis_kelamin' => $request->jenis_kelamin,
                'tentang' => $request->tentang,
                'image' => $fileName,
                'password' => bcrypt($request->password),
                'address' => $request->address,
                'contact' => $request->contact,
                'id_belajar' => $request->id_belajar,
                'role' => $request->role,
                'active' => $request->active,
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
            'image'           => 'image|mimes:jpeg,png,jpg|max:3000'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {

            $user = Auth::user();

            // Pastikan hanya pengguna saat ini yang dapat mengubah datanya sendiri
            if ($user->id != $id) {
                return $this->error("Unauthorized", "Anda tidak memiliki izin untuk mengubah password pengguna lain!", 403);
            }

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

            $datas['name'] = $request->name ?: $datas->name;;
            $datas['username'] = $request->username ?: $datas->username;
            $datas['email'] = $request->email ?: $datas->email;
            $datas['address'] = $request->address ?: $datas->address;;
            $datas['contact'] = $request->contact ?: $datas->contact;
            $datas['jenis_kelamin'] = $request->jenis_kelamin ?: $datas->jenis_kelamin;
            $datas['tentang'] = $request->tentang ?: $datas->tentang;
            $datas['id_belajar'] = $request->id_belajar ?: $datas->id_belajar;
            $datas['role'] = $request->role ?: $datas->role;
            $datas['active'] = $request->active ?: $datas->active;
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
            'new_password'    => 'required',
            'old_password'    => 'required',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $user = Auth::user();

            // Pastikan hanya pengguna saat ini yang dapat mengubah datanya sendiri
            if ($user->id != $id) {
                return $this->error("Unauthorized", "Anda tidak memiliki izin untuk mengubah password pengguna lain!", 403);
            }

            // Cari data pengguna berdasarkan ID
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            // Validasi password lama
            if (!Hash::check($request->old_password, $datas->password)) {
                return $this->error("FAILED", "Password Lama Salah", 422);
            }

            // Update password
            $datas->password = bcrypt($request->new_password);
            $datas->updated_by = $user->id;

            if ($datas->save()) {
                // Hapus cache jika ada
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Password Berhasil diperbaharui!", $datas);
            }

            return $this->error("FAILED", "Password Gagal diperbaharui!", 400);
        } catch (Exception $e) {
            return $this->error("Error", $e->getMessage(), 500);
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

    public function statusUser($id)
    {

        try {

            // search
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            $datas['active'] = (int)!$datas->active;
            $datas['edited_by'] = Auth::user()->id;

            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Status User  Berhasil diubah!", $datas);
            }

            return $this->error("FAILED", "Status User Gagal direset!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!" . $e->getMessage(), $e->getMessage());
        }
    }

    public function instruktor($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getSearch = $request->search;
            $getByCategory = $request->category;
            $getByFilter = $request->filter;
            $getByTopics = $request->topics;
            $getByUser = $request->user_id;

            $page = $request->page;
            $paginate = $request->paginate;
            // $clientIpAddress = $request->getClientIp();

            $params =  ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Category=" . $getByCategory . ",#Topics=" . $getByTopics . ",#User=" . $getByUser  .  ",#Search=" . $getSearch;

            $key = $this->keyRedis . "Instruktor" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Data Instruktor By {$params} from (CACHE)", $result);
            }

            // Ambil data dari database
            $query = User::whereHas('medias')->with('medias');

            // Step 4: Apply search filter
            if ($request->filled('user_id')) {
                $query->where('id',  $getByUser);
            }

            // Step 5: Apply category filter
            if ($request->filled('category')) {
                $query->whereHas('ctg_book', function ($queryCategory) use ($request) {
                    return $queryCategory->where('slug', Str::slug($request->category));
                });
            }

            // Step 9: Paginate or limit the results
            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }
            // $data = Helper::queryModifyUserForDatas($datas, true);

            $datas = Self::queryGetModify($result, $setPaginate, true);

            // Step 11: Cache the results in Redis
            Redis::set($key, json_encode($datas));
            Redis::expire($key,  $this->expired);

            return $this->success("List Data Instruktor", $datas);
        } catch (\Throwable $e) { // Gunakan \Throwable untuk menangkap semua jenis error
            return $this->error("Internal Server Error" . $e->getMessage(), $e->getMessage(), 500);
        }
    }

    function queryGetModify($datas, $paginate, $manyResult = false)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');

                $modifiedData = array_map(function ($item) {
                    // $item->berita_link = env('NEWS_LINK') . $item->slug;
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
                // return $datas;
                self::modifyData($datas);
            }
            return $datas;
        }
    }

    function modifyData($item)
    {

        // $ctg_book_id = [
        //     'id' => $item['ctg_book_id'],
        //     'name' => self::queryGetCategory($item['ctg_book_id'])->title_category,
        //     'slug' => self::queryGetCategory($item['ctg_book_id'])->slug,
        // ];
        // $item->ctg_book_id = $ctg_book_id;

        // $user_id = [
        //     'name' => Helper::queryGetUser($item['user_id']),
        // ];
        // $item->user_id = $user_id;
        // $item->image = Helper::convertImageToBase64('images/', $item->image);
        // $item = Helper::queryGetUserModify($item);
        $item->created_by = optional($item->createdBy)->only(['id', 'name']);
        $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
        // $item->topic->makeHidden('pivot');

        unset($item->createdBy, $item->editedBy, $item->deleted_at);

        return $item;
    }
}
