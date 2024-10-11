<?php

namespace App\Repositories\Pemohon;

use App\Repositories\Pemohon\PemohonInterface as PemohonInterface;
use App\Models\Pemohon;
use App\Models\User;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Services\EmailService;
use App\Helpers\Helper;
use App\Models\Ctg_Information;
use App\Models\Ctg_Pemohon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\Pemohon_ReqNotification;
use App\Mail\Pemohon_ResNotification;
use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;




class PemohonRepository implements PemohonInterface
{

    protected $pemohon;
    protected $generalRedisKeys;
    protected $pmhCode;


    // Response API HANDLER
    use API_response;

    public function __construct(Pemohon $pemohon)
    {
        $this->pemohon = $pemohon;
        $this->generalRedisKeys = "pemohon_";
        $this->pmhCode = "PMH-";
    }

    public function getPemohon($request)
    {
        // $limit = Helper::limitDatas($request);
        $limit = Helper::limitDatas($request);

        if (($request->order != null) or ($request->order != "")) {
            $order = $request->order == "desc" ? "desc" : "asc";
        } else {
            $order = "desc";
        }
        $getCtgPemohon = $request->ctg_pemohon;
        $getCtgInformation = $request->ctg_informasi;
        $getCode = $request->code;
        $getId = $request->id;
        $getStatus = $request->status;
        $getTrash = $request->trash;
        $getRestore = $request->restore;
        $getRestoreId = $request->restoreid;


        switch (true) {
            case $getCtgInformation !== null && $getCtgInformation !== '""' && $getCtgInformation !== "":
                if ($getStatus !== null && $getStatus !== '""' && $getStatus !== "") {
                    return self::getAllByStatusInCtg($getCtgInformation, $getStatus, $order, $limit);
                }
                return self::getAllPemohonByCtg_Information($getCtgInformation, $order, $limit);
            case $getCtgPemohon !== null && $getCtgPemohon !== '""' && $getCtgPemohon !== "":
                return self::getAllPemohonByCtg_Pemohon($getCtgPemohon, $order, $limit);
            case $getCode !== null && $getCode !== '""' && $getCode !== "":
                return self::findPemohonByCode($getCode);
            case $getId !== null && $getId !== '""' && $getId !== "":
                return self::findById($getId);
            case $getStatus !== null && $getStatus !== '""' && $getStatus !== "":
                return self::getAllByStatus($getStatus, $order, $limit);
            case $getTrash !== null && $getTrash !== '""' && $getTrash !== "":
                return self::getAllTrashPemohons($order, $limit);
            case $getRestore !== null && $getRestore !== '""' && $getRestore !== "":
                return self::restore();
            case $getRestoreId !== null && $getRestoreId !== '""' && $getRestoreId !== "":
                return self::restoreById($getRestoreId);
            default:
                return self::getAllPemohons($order, $limit);
        }
    }

    // getAll
    public function getAllPemohons($order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;

            $keyAll = $key . "All_" . request()->get("page", 1);
            if (Redis::exists($keyAll)) {
                $result = json_decode(Redis::get($keyAll));
                return $this->success("List Keseluruhan Pemohon from (CACHE)", $result);
            }
            $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->orderBy('created_at', $order)
                ->paginate($limit);

            if ($pemohon) {
                $modifiedData = $pemohon->items();
                $modifiedData = array_map(function ($item) {

                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAll : $keyAll;
                Redis::setex($key, 60, json_encode($pemohon));

                return $this->success("List keseluruhan Pemohon", $pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // getAll
    public function getAllTrashPemohons($order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;

            $keyAll = $key . "All_Trash_" . request()->get("page", 1);
            if (Redis::exists($keyAll)) {
                $result = json_decode(Redis::get($keyAll));
                return $this->success("List Keseluruhan Pemohon Trash from (CACHE)", $result);
            }
            $pemohon = Pemohon::onlyTrashed()->with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->orderBy('created_at', $order)
                ->paginate($limit);

            if ($pemohon) {
                $modifiedData = $pemohon->items();
                $modifiedData = array_map(function ($item) {

                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAll : $keyAll;
                Redis::setex($key, 60, json_encode($pemohon));

                return $this->success("List keseluruhan Pemohon", $pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    //filterByCategory
    public function getAllPemohonByCtg_Pemohon($slug, $order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . 'ctgPemohon_All_' . $slug)) {
                $result = json_decode(Redis::get($key . 'ctgPemohon_All_' . $slug));
                return $this->success("List Keseluruhan Pemohon berdasarkan Kategori Pemohon dengan Slug = ($slug) from (CACHE)", $result);
            }

            $category = Ctg_Pemohon::where('slug', $slug)->first();
            if ($category) {
                $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                    ->where('ctg_pemohon_id', $category->id)
                    ->orderBy('created_at', $order)
                    ->paginate($limit);

                $modifiedData = $pemohon->items();
                $modifiedData = array_map(function ($item) {
                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . 'ctgPemohon_All_' . $slug  : $key . 'ctgPemohon_All_' . $slug;
                Redis::setex($key, 60, json_encode($pemohon));

                return $this->success("List Keseluruhan Pemohon berdasarkan Kategori Pemohon dengan Slug = ($slug)", $pemohon);
            } else {
                return $this->error("Not Found", "Pemohon berdasarkan Kategori Pemohon dengan Slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllPemohonByCtg_Information($slug, $order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . 'ctgInformation_All_' . $slug)) {
                $result = json_decode(Redis::get($key . 'ctgInformation_All_' . $slug));
                return $this->success("List Keseluruhan Pemohon berdasarkan Kategori Informasi dengan Slug = ($slug) from (CACHE)", $result);
            }

            $category = Ctg_Information::where('slug', $slug)->first();
            if ($category) {
                $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                    ->where('ctg_information_id', $category->id)
                    ->orderBy('created_at', $order)
                    ->paginate($limit);
                if ($pemohon) {
                    $modifiedData = $pemohon->items();
                    $modifiedData = array_map(function ($item) {
                        $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                        $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                        $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                        unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                        return $item;
                    }, $modifiedData);

                    $key = Auth::check() ? $keyAuth . 'ctgInformation_All_' . $slug  : $key . 'ctgInformation_All_' . $slug;
                    Redis::setex($key, 60, json_encode($pemohon));

                    return $this->success("List Keseluruhan Pemohon berdasarkan Kategori Informasi dengan Slug = ($slug)", $pemohon);
                }
            } else {
                return $this->error("Not Found", "Pemohon berdasarkan Kategori Informasi dengan Slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //getAll by status
    public function getAllByStatus($status, $order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key . $status)) {
                $result = json_decode(Redis::get($key . $status));
                return $this->success("List keseluruhan Pemohon dengan status = ($status) from (CACHE)", $result);
            }

            $status = Str::slug($status);
            // $checkPemohon = Pemohon::find($status);
            // if ($checkPemohon) {
            $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->where('status', $status)
                ->orderBy('created_at', $order)
                ->paginate($limit);
            if ($pemohon) {
                $modifiedData = $pemohon->items();
                $modifiedData = array_map(function ($item) {
                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $status  : $key . $status;
                Redis::setex($key, 60, json_encode($pemohon));

                return $this->success("List keseluruhan Pemohon dengan status = ($status)", $pemohon);
            } else {
                return $this->error("Not Found", "Pemohon berdasarkan status = ($status) tidak ditemukan!", 404);
            }
            // } 
            // 
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //get All by status in category
    public function getAllByStatusInCtg($slug, $status, $order, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key .  $slug . "_" .  $status)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $status));
                return $this->success("List keseluruhan Pemohon dengan status = ($status) dalam Kategori ($slug) from (CACHE)", $result);
            }

            $status = Str::slug($status);
            $category = Ctg_Information::where('slug', $slug)->first();

            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }
            // $checkPemohon = Pemohon::find($status);
            // if ($checkPemohon) {
            $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->where('ctg_information_id', $category->id)
                ->where('status', $status)
                ->orderBy('created_at', $order)
                ->paginate($limit);
            if ($pemohon) {
                $modifiedData = $pemohon->items();
                $modifiedData = array_map(function ($item) {
                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug . "_" .  $status  : $key . $slug . "_" .  $status;
                Redis::setex($key, 60, json_encode($pemohon));

                return $this->success("List keseluruhan Pemohon dengan status = ($status) dalam Kategori ($slug)", $pemohon);
            } else {
                return $this->error("Not Found", "Pemohon berdasarkan status = ($status) dalam Kategori ($slug) tidak ditemukan!", 404);
            }
            // } 
            // 
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
    //searchByKeywords
    public function findPemohonByCode($codeId)
    {
        try {
            $key = $this->generalRedisKeys;
            // $codeId = $codeId->code;
            if (Redis::exists($key . $codeId)) {
                $result = json_decode(Redis::get($key . $codeId));
                return $this->success("Detail Pemohon dengan Kode_Permohonan = ($codeId) from (CACHE)", $result);
            }
            $pemohon = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->where('kode_permohonan', $codeId)
                ->latest('created_at')
                ->get();

            if ($pemohon->isNotEmpty()) {
                $modifiedData = $pemohon->map(function ($item) {
                    $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                    $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                    unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                    return $item;
                });

                Redis::set($key . $codeId, json_encode($pemohon));
                Redis::expire($key . $codeId, 60); // Cache for 1 minute

                return $this->success("Detail Pemohon dengan Kode_Permohonan = ($codeId)", $pemohon);
            } else {
                return $this->error("Not Found", "Pemohon dengan code = ($codeId) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function findPemohonByEmailAndNik($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'nik'             =>  'required',
                'email'           =>  'required',
            ],
            [
                'nik.required'    => 'nik tidak boleh kosong',
                'email.required'  => 'email tidak boleh kosong',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }
        try {
            $email = $request->email;
            $nik = $request->nik;
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $email . '&' . $nik)) {
                $result = json_decode(Redis::get($key . $email . '&' . $nik));
                return $this->success("Detail Pemohon dengan Email = ($email) dan NIK = ($nik) from CACHE", $result);
            }

            // $allNiksFromDatabase = Pemohon::pluck('nik')->toArray();
            // $matchingNiks = array_filter($allNiksFromDatabase, function ($hashedNik) use ($nik) {
            //     return Hash::check($nik, $hashedNik);
            // });
            // $matchingPemohons = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
            //     ->where('email', $email)
            //     ->whereIn('nik', $matchingNiks)
            //     ->get();

            $matchingPemohons = Pemohon::with(['approvedBy', 'ctgInformation', 'ctgPemohon'])
                ->where('email', $email)
                ->where('nik', $nik)
                ->get();
            if (empty($matchingPemohons)) {
                return $this->error("Not Found", "Email dan NIK tidak terdaftar di database kami", 404);
            }

            $modifiedData = $matchingPemohons->map(function ($item) {
                $item->ctg_pemohon_id = optional($item->ctgPemohon)->only(['id', 'title_category']);
                $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category']);
                $item->approved_by = optional($item->approvedBy)->only(['id', 'name']);

                unset($item->approvedBy, $item->ctgPemohon, $item->ctgInformation);
                return $item;
            });

            // gaya Paginasi baru :(
            $perPage = 12;
            $page = request('page', 1);
            $modifiedData = collect($modifiedData);
            $pagedData = $modifiedData->forPage($page, $perPage);
            $finalData = new LengthAwarePaginator(
                $pagedData,
                $modifiedData->count(),
                $perPage,
                $page,
                ['path' => url()->current()]
            );

            $key = Auth::check() ? $keyAuth . $email . '&' . $nik : $key . $email . '&' . $nik;
            Redis::setex($key, 60, json_encode($finalData));

            return $this->success("Detail Pemohon dengan Email = ($email) dan NIK = ($nik)", $finalData);
            // }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("Detail Pemohon dengan ID = ($id) from (CACHE)", $result);
            }

            $pemohon = Pemohon::find($id);
            if ($pemohon) {
                $approvedBy = User::select(['id', 'name'])->find($pemohon->approved_by);
                $ctgInformation = Ctg_Information::select(['id', 'title_category'])->find($pemohon->ctg_information_id);
                $ctg_pemohon_id = Ctg_Pemohon::select(['id', 'title_category'])->find($pemohon->ctg_pemohon_id);

                $pemohon->approved_by = optional($approvedBy)->only(['id', 'name']);
                $pemohon->ctg_information_id = optional($ctgInformation)->only(['id', 'title_category']);
                $pemohon->ctg_pemohon_id = optional($ctg_pemohon_id)->only(['id', 'title_category']);

                // 'ctgInformation', 'ctgPemohon'

                Redis::set($key . $id, json_encode($pemohon));
                Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Detail Pemohon dengan ID = ($id)", $pemohon);
            } else {
                return $this->error("Not Found", "Detail Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createPemohon($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  =>  'required',
                'nik'                   =>  'required',
                'email'                 =>  'required',
                'contact'               =>  'required',
                'judul_informasi'       =>  'required',
                'rincian_informasi'     =>  'required',
                'tujuan_penggunaan'     =>  'required',
                'file'                  =>  'mimes:pdf|
                                            max:5120',
            ],
            [
                'name.required'              => 'name tidak boleh kosong',
                'nik.required'               => 'nik tidak boleh kosong',
                'email.required'             => 'email tidak boleh kosong',
                'contact.required'           => 'contact tidak boleh kosong',
                'judul_informasi.required'   => 'judul_informasi tidak boleh kosong',
                'rincian_informasi.required' => 'rincian_informasi tidak boleh kosong',
                'tujuan_penggunaan.required' => 'tujuan_penggunaan tidak boleh kosong',
                'file.mimes'                 => 'Format File tidak didukung!,mohon inputkan File bertipe pdf',
                'file.max'                   => 'File terlalu besar, maksimal 5MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {
            $pemohon = new Pemohon();
            $pemohon->name = $request->name; //required
            $pemohon->address = $request->address;
            $pemohon->email = $request->email; //required
            $pemohon->contact = $request->contact; //required
            $pemohon->job = $request->job;
            $pemohon->judul_informasi = $request->judul_informasi; //required
            $pemohon->rincian_informasi = $request->rincian_informasi; //required
            $pemohon->tujuan_penggunaan = $request->tujuan_penggunaan; //required
            $pemohon->tujuan_opd = $request->tujuan_opd;
            $pemohon->keterangan = $request->keterangan;
            $pemohon->nik = $request->nik; //required
            // $pemohon->nik = bcrypt($request->nik);

            // Cek panjang nik
            if (strlen($request->nik) > 16) {
                return $this->error("Bad Request", "Mohon cek kembali NIK anda!", 404);
            }
            //DEFAULT-SET
            $pemohon->cara_memperoleh_informasi = $request->cara_memperoleh_informasi ?? 'Melihat'; //auto
            $pemohon->mendapatkan_salinan_informasi = $request->mendapatkan_salinan_informasi ?? 'Softcopy'; //auto
            $pemohon->cara_mendapatkan_salinan_informasi = $request->cara_mendapatkan_salinan_informasi ?? 'WhatsApp'; //auto
            //auto generate

            $code = $this->pmhCode;
            $pemohon->kode_permohonan = $code . Helper::generateCode(5, Pemohon::class, "kode_permohonan");
            $ctg_pemohon_id = $request->ctg_pemohon_id;
            $category = Ctg_Pemohon::find($ctg_pemohon_id);
            if ($category) {
                $pemohon->ctg_pemohon_id = $ctg_pemohon_id;
            } else {
                return $this->error("Not Found", "Kategori Pemohon dengan ID = ($ctg_pemohon_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('file')) {
                $destination = 'public/files';
                $file = $request->file('file');
                $fileName = time() . "." . $file->getClientOriginalExtension();

                $pemohon->file = $fileName;
                //storeOriginal
                $file->storeAs($destination, $fileName);

                // compress to thumbnail 
                // Helper::resizeImage($file, $fileName, $request);
            }
            // Simpan objek Pemohon
            $create = $pemohon->save();

            // contact to View
            $twitterContact = Contact::pluck('twitter')->implode(', ');
            $facebookContact = Contact::pluck('facebook')->implode(', ');
            $instagramContact = Contact::pluck('instagram')->implode(', ');
            $emailContact = Contact::pluck('email')->implode(', ');
            $contact = Contact::pluck('contact')->implode(', ');
            $address = Contact::pluck('address')->implode(', ');
            $website = Contact::pluck('website')->implode(', ');
            $nama_dinas = Setting::pluck('name_dinas')->implode(', ');
            $toView = [
                'kode_permohonan' => $pemohon->kode_permohonan,
                'name' => $pemohon->name,
                'email' => $emailContact,
                'facebook' => $facebookContact,
                'twitter' => $twitterContact,
                'instagram' => $instagramContact,
                'contact' => $contact,
                'address' => $address,
                'website' => $website,
                'nama_dinas' => $nama_dinas,
            ];
            //Email
            // $view = view('emails.reqPermohonanMail')->with('dataView', $toView);
            // $renderHtml = $view->render();

            // Cek kondisi $create
            if ($create) {
                // sendMail
                // $email = $pemohon->email;
                // $receiver = $pemohon->name;
                // $subject = 'Konfirmasi Permohonan Informasi';

                // $sendEmail = EmailService::sendEmail($email, $renderHtml, $receiver, $subject);
                RedisHelper::dropKeys($this->generalRedisKeys);
                // $responseMessage = $sendEmail['success'] ? "Mohon cek inbox atau spam!" : "Masalah terdeteksi di Email Service, untuk lebih lanjut mohon cek Log email.";
                // return $this->success("Pemohon Berhasil ditambahkan, {$sendEmail['message']} ke user ($pemohon->email). $responseMessage", $pemohon);
                return $this->success("Pemohon Berhasil ditambahkan!", $pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updatePemohon($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name'                  =>  'required',
                'contact'               =>  'required',
                'judul_informasi'       =>  'required',
                'rincian_informasi'     =>  'required',
                'tujuan_penggunaan'     =>  'required',
                'file'                  =>  'mimes:pdf|
                                            max:5120',
            ],
            [
                'name.required'              => 'name tidak boleh kosong',
                'contact.required'           => 'contact tidak boleh kosong',
                'judul_informasi.required'   => 'judul_informasi tidak boleh kosong',
                'rincian_informasi.required' => 'rincian_informasi tidak boleh kosong',
                'tujuan_penggunaan.required' => 'tujuan_penggunaan tidak boleh kosong',
                'file.mimes'                 => 'Format File tidak didukung!, mohon inputkan File bertipe pdf',
                'file.max'                   => 'File terlalu besar, maksimal 5MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {

            // search
            $pemohon = Pemohon::find($id);
            // Check if the pemohon exists
            if (!$pemohon) {
                return $this->error("Not Found", "Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            }
            // dd($pemohon->kode_permohonan);

            // Checking Category_id
            $id = $request->ctg_pemohon_id;
            $categoryPemohon = Ctg_Pemohon::find($id);
            if (!$categoryPemohon) {
                return $this->error("Not Found", "Kategori Pemohon ID = ($id) tidak ditemukan!", 404);
            }
            // dd(Auth::user()->id);

            $ctg_information_id = $request->ctg_information_id;
            $categoryInformation = Ctg_Information::find($ctg_information_id);
            if ($categoryInformation) {
                $pemohon->ctg_information_id = $ctg_information_id;
            } else {
                return $this->error("Not Found", "Kategori Informasi dengan ID = ($ctg_information_id) tidak ditemukan!", 404);
            }
            // processing new image
            if ($request->hasFile('file')) {
                if ($pemohon->file) {
                    Storage::delete('public/files/' . $pemohon->file);
                    // Storage::delete('public/thumbnails/t_images/' . $pemohon->image);
                }
                $destination = 'public/files';
                $file = $request->file('file');
                $fileName = time() . "." . $file->getClientOriginalExtension();
                // dd($fileName);

                $pemohon->file = $fileName;
                // $pemohon->file_type = $file->getClientOriginalExtension();
                $pemohon->status = "Disetujui";
                $pemohon->url = env('FILE_URL') . env("OPD_CODE") . '/' . Str::slug($request->judul_informasi, '-');

                //storeOriginal
                $file->storeAs($destination, $fileName);

                //compressImage
                // Helper::resizeImage($file, $fileName, $request);
            } else {
                $pemohon->status = $request->status ?? "Diproses";
                if ($request->delete_file) {
                    Storage::delete('public/files/' . $pemohon->file);
                    $pemohon->file = null;
                }
                $pemohon->file = $pemohon->file;
            }

            // approved
            $pemohon['name'] = $request->name; //required
            $pemohon['address'] = $request->address;
            $pemohon['email'] = $request->email; //required
            $pemohon['contact'] = $request->contact; //required
            $pemohon['job'] = $request->job;
            $pemohon['judul_informasi'] = $request->judul_informasi; //required
            $pemohon['rincian_informasi'] = $request->rincian_informasi; //required
            $pemohon['tujuan_penggunaan'] = $request->tujuan_penggunaan; //required
            $pemohon['cara_memperoleh_informasi'] = $request->cara_memperoleh_informasi ?? $pemohon->cara_memperoleh_informasi; //required
            $pemohon['mendapatkan_salinan_informasi'] = $request->mendapatkan_salinan_informasi ?? $pemohon->mendapatkan_salinan_informasi; //required
            $pemohon['cara_mendapatkan_salinan_informasi'] = $request->cara_mendapatkan_salinan_informasi ?? $pemohon->cara_mendapatkan_salinan_informasi; //required
            $pemohon['tujuan_opd'] = $request->tujuan_opd;
            $pemohon['keterangan'] = $request->keterangan;
            $pemohon['nik'] = $request->has('nik') ? $request->nik : $pemohon->nik;
            // $pemohon['nik'] = $request->has('nik') ? bcrypt($request->nik) : $pemohon->nik;
            // $pemohon['ctg_information'] = $request->Information;
            // if ($request->nik) {
            //     $pemohon['nik'] = bcrypt($request->nik);
            // } else {
            //     $pemohon['nik'] = $pemohon->nik;
            // }

            // Cek panjang nik
            if (strlen($request->nik) > 16) {
                return $this->error("Bad Request", "Mohon cek kembali NIK anda!", 404);
            }
            //auto generate
            $pemohon['kode_permohonan'] = $pemohon->kode_permohonan;
            $pemohon['approved_by'] = Auth::user()->id;

            $update = $pemohon->save();

            // contact to View
            $twitterContact = Contact::pluck('twitter')->implode(', ');
            $facebookContact = Contact::pluck('facebook')->implode(', ');
            $instagramContact = Contact::pluck('instagram')->implode(', ');
            $emailContact = Contact::pluck('email')->implode(', ');
            $contact = Contact::pluck('contact')->implode(', ');
            $address = Contact::pluck('address')->implode(', ');
            $website = Contact::pluck('website')->implode(', ');
            $nama_dinas = Setting::pluck('name_dinas')->implode(', ');
            $toView = [
                'kode_permohonan' => $pemohon->kode_permohonan,
                'name' => $pemohon->name,
                'status' => $pemohon->status,
                'email' => $emailContact,
                'facebook' => $facebookContact,
                'twitter' => $twitterContact,
                'instagram' => $instagramContact,
                'contact' => $contact,
                'address' => $address,
                'website' => $website,
                'nama_dinas' => $nama_dinas,
            ];
            //Email
            // $view = view('emails.resPermohonanMail')->with('dataView', $toView);
            // $renderHtml = $view->render();

            if ($update) {
                // sendMail
                // $email = $pemohon->email;
                // $receiver = $pemohon->name;
                // $subject = 'Penerbitan Hasil Permohonan Informasi';
                // Mail::to($request->email)->send(new Pemohon_ResNotification($dataView));

                // $sendEmail = EmailService::sendEmail($email, $renderHtml, $receiver, $subject);
                RedisHelper::dropKeys($this->generalRedisKeys);

                // $responseMessage = $sendEmail['success'] ? "Mohon cek inbox atau spam!" : "Masalah terdeteksi di Email Service, untuk lebih lanjut mohon cek Log email.";
                // return $this->success("Pemohon Berhasil diperbaharui {$sendEmail['message']} ke user ($pemohon->email). $responseMessage", $pemohon);
                return $this->success("Pemohon Berhasil diperbaharui!", $pemohon);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // delete
    public function deletePemohon($id)
    {
        try {

            // search
            $pemohon = Pemohon::find($id);
            // return dd($pemohon);
            if (!$pemohon) {
                return $this->error("Not Found", "Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            }

            $del = $pemohon->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED!", "Pemohon dengan ID = ($id) Berhasil dihapus!");
            }
            // approved
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deletePermanent($id)
    {
        try {

            // search
            $pemohon = Pemohon::onlyTrashed()->find($id);
            // return dd($pemohon);
            if (!$pemohon) {
                return $this->error("Not Found", "Pemohon dengan ID = ($id) tidak ditemukan!", 404);
            }


            $del = $pemohon->forceDelete();
            if ($del) {
                if ($pemohon->file) {
                    Storage::delete('public/files/' . $pemohon->file);
                    // Storage::delete('public/thumbnails/t_files/' . $pemohon->file);
                }
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED!", "Pemohon dengan ID = ($id) Berhasil dihapus!");
            }
            // approved
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Pemohon::onlyTrashed();
            if ($data->restore()) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Restore Pemohon Berhasil!");
            }
            return $this->error("FAILED", "Restore Pemohon Gagal!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Pemohon::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Restore Pemohon dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Pemohon dengan ID = ($id) Gagal!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
