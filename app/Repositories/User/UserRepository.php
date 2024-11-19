<?php

namespace App\Repositories\User;

use App\Helpers\Helper;
use App\Helpers\AuthHelper;
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

    public function getUser($request)
    {
        try {
            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getSearch = $request->search;
            $getById = $request->id;
            $getTrash = $request->trash;
            $page = $request->page;
            $paginate = $request->paginate;
            // $clientIpAddress = $request->getClientIp();

            $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Search=" . $getSearch;

            $key = $this->keyRedis . "All" . Auth::user()->username . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List User By {$params} from (CACHE)", $result);
            }
            $sqlQuery = User::orderBy('created_at', $order)->withCount('medias');

            if (Auth::user()->role != "Admin") {
                $sqlQuery = User::where('id', Auth::user()->id);
            }
            // Step 3: Set the query based on trash filter
            if ($request->filled('trash') && $request->trash == "true") {
                $query = $sqlQuery->onlyTrashed();
            } else {
                $query = $sqlQuery;
            }

            // Step 4: Apply search filter
            if ($request->filled('search')) {
                $query->where('username', 'LIKE', '%' . $getSearch . '%');
            }



            // Step 8: Apply id filter and increment views if not already viewed
            if ($request->filled('id')) {
                $currentUser = auth()->user();
                if ($currentUser->id !== $getById && $currentUser->role !== "Admin") {
                    return $this->error("Unauthorized", "Anda tidak memiliki hak untuk melihat data ini!", 403);
                }
                $query->where('id', $getById)->first();
            }

            // Step 9: Paginate or limit the results
            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }

            // Step 10: Modify the result (optional)
            $datas = Self::queryGetModify($result, $setPaginate, true);

            // Step 11: Cache the results in Redis
            Redis::set($key, json_encode($datas));
            Redis::expire($key,  $this->expired);

            return $this->success("List Berita By {$params}", $datas);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!" . $e->getMessage(), "");
        }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>]).+$/'
            ],
            'jenis_kelamin'           => 'required',
            'confirm_password' => 'required|same:password',
            'image'           => 'image|mimes:jpeg,png,jpg|max:3072',
            'username' => [
                'required',
                'string',
                'min:5',
                'max:20',
                'regex:/^[a-zA-Z0-9_.]+$/',
                'not_regex:/^[_.]|[_.]$/',
                'unique:users,username',
            ],
        ], [
            // Custom error messages
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email sudah digunakan. Pilih email lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password harus memiliki minimal 8 karakter.',
            'password.regex' => 'Password harus mengandung setidaknya satu huruf kapital dan satu karakter khusus seperti !@#$%^&*(),.?":{}|<>.',
            'confirm_password.required' => 'Konfirmasi password wajib diisi.',
            'confirm_password.same' => 'Konfirmasi password harus sama dengan password.',
            'username.required' => 'Username wajib diisi.',
            'username.string' => 'Username harus berupa teks.',
            'username.min' => 'Username minimal 5 karakter.',
            'username.max' => 'Username maksimal 20 karakter.',
            'username.regex' => 'Username hanya boleh menggunakan huruf, angka, garis bawah (_) atau titik (.) tanpa spasi.',
            'username.not_regex' => 'Username tidak boleh diawali atau diakhiri dengan garis bawah (_) atau titik (.)',
            'username.unique' => 'Username sudah digunakan. Pilih username lain.',
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
                'facebook' => $request->facebook,
                'instagram' => $request->instagram,
                'twitter' => $request->twitter,
                'linkedin' => $request->linkedin,
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
            'email'     => 'email',
            'image'           => 'image|mimes:jpeg,png,jpg|max:3000',
            'username' => [
                'string',
                'min:5',
                'max:20',
                'regex:/^[a-zA-Z0-9_.]+$/',
                'not_regex:/^[_.]|[_.]$/',
            ],
        ], [
            'username.string' => 'Username harus berupa teks.',
            'username.min' => 'Username minimal 5 karakter.',
            'username.max' => 'Username maksimal 20 karakter.',
            'username.regex' => 'Username hanya boleh menggunakan huruf, angka, garis bawah (_) atau titik (.) tanpa spasi.',
            'username.not_regex' => 'Username tidak boleh diawali atau diakhiri dengan garis bawah (_) atau titik (.)',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {


            // Fetch user data
            $datas = User::find($id);

            // Check if the user exists
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            AuthHelper::isOwnerData($datas);


            // Validate username if provided
            if ($request->filled('username')) {
                if (User::where('username', $request->username)->exists()) {
                    return $this->error("Validation Failed", "Username sudah dipakai", 422);
                }
            }

            // Validate email if provided
            if ($request->filled('email')) {
                if (User::where('email', $request->email)->exists()) {
                    return $this->error("Validation Failed", "Email sudah dipakai", 422);
                }
            }

            // Validate password
            if (!Hash::check($request->password, $datas->password)) {
                return $this->error("Validation Failed", "Password Anda salah", 403);
            }

            $fileName = $request->hasFile('image') ? "user_" . time() . "." . $request->image->getClientOriginalExtension() : "";

            $datas['name'] = $request->name ?: $datas->name;
            $datas['username'] = $request->username ?: $datas->username;
            $datas['email'] = $request->email ?: $datas->email;
            $datas['address'] = $request->address ?: $datas->address;
            $datas['contact'] = $request->contact ?: $datas->contact;
            $datas['facebook'] = $request->facebook ?: $datas->facebook;
            $datas['instagram'] = $request->instagram ?: $datas->instagram;
            $datas['twitter'] = $request->twitter ?: $datas->twitter;
            $datas['linkedin'] = $request->linkedin ?: $datas->linkedin;
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
            'new_password'    => [
                'required',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>]).+$/'
            ],
            'old_password'    => 'required',
            'confirm_password' => 'required|same:new_password',
        ], [
            'new_password.required' => 'Password wajib diisi.',
            'new_password.min' => 'Password harus memiliki minimal 8 karakter.',
            'new_password.regex' => 'Password harus mengandung setidaknya satu huruf kapital dan satu karakter khusus seperti !@#$%^&*(),.?":{}|<>.',
            'confirm_password.required' => 'Konfirmasi password wajib diisi.',
            'confirm_password.same' => 'Konfirmasi password harus sama dengan password.',
        ]);

        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $user = Auth::user();


            // Cari data pengguna berdasarkan ID
            $datas = User::find($id);
            if (!$datas) {
                return $this->error("Not Found", "User dengan ID = ($id) tidak ditemukan!", 404);
            }

            AuthHelper::isOwnerData($datas);

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
                return $this->success("Password Berhasil diperbarui!", $datas);
            }

            return $this->error("FAILED", "Password Gagal diperbarui!", 400);
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

            $datas['password'] = bcrypt("Qwerty123456!");
            $datas['updated_by'] = Auth::user()->id;

            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Password Berhasil direset dengan: Qwerty123456!", $datas);
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
            $query = User::whereHas('medias')->with('medias')->withCount('medias');

            // Step 4: Apply search filter
            if ($request->filled('user_id')) {
                $query->where('id',  $getByUser);
            }

            // Step 5: Apply category filter
            // if ($request->filled('category')) {
            //     $query->whereHas('ctg_book', function ($queryCategory) use ($request) {
            //         return $queryCategory->where('slug', Str::slug($request->category));
            //     });
            // }

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

        if (!Auth::check()) {

            $item->makeHidden([
                'created_by',       // Hide 'created_by' field
                'edited_by',       // Hide 'created_by' field
                'created_at',       // Hide 'created_at' field
                'updated_at',       // Hide 'updated_at' field
                'email_verified_at', // Hide 'email_verified_at' field
                'deleted_at',          // Hide 'tentang' field
                'id_belajar',       // Hide 'id_belajar' field
                'last_login',       // Hide 'last_login' field
                'username',       // Hide 'last_login' field
                'email',       // Hide 'last_login' field
                'active',       // Hide 'last_login' field
            ]);
            // unset($item->id, $item->created_by, $item->edited_by, $item->deleted_at);

            // $item = $item->only(['id', 'name', 'medias']);

            // Format 'medias' as needed (assuming you need to format the media objects as well)

            foreach ($item->medias as $media) {
                // Customize this part to include or exclude fields as needed
                $media->makeHidden([
                    'created_by',       // Hide 'created_by' field
                    'edited_by',       // Hide 'created_by' field
                    'created_at',       // Hide 'created_at' field
                    'updated_at',       // Hide 'updated_at' field
                    'user_id', // Hide 'email_verified_at' field
                    "like_count",
                    'comment_count',
                    'rate_count',
                    'report_stat',
                ]);
                // unset($media->created_by, $media->edited_by); // For example, remove created_by and edited_by from media

            }
        }
        $totalLikes = $item->medias->sum('like_count');
        // Add the total likes as a new attribute
        $item->total_likes = $totalLikes;

        return $item;
    }
}
