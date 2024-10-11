<?php

namespace App\Repositories\Information;

use App\Repositories\Information\InformationInterface as InformationInterface;
use App\Models\Information;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Information;




class InformationRepository implements InformationInterface
{

    protected $information;
    protected $generalRedisKeys;
    protected $infCode;

    // Response API HANDLER
    use API_response;

    public function __construct(Information $information)
    {
        $this->information = $information;
        $this->generalRedisKeys = "information_";
        $this->infCode = "INF-";
    }

    public function getInformations($request)
    {
        $limit = Helper::limitDatas($request);
        $getKeyword = $request->search;
        $getCategory = $request->category;
        $getSlug = $request->slug;
        $getId = $request->id;
        $getMonth = $request->bulan;
        $getYear = $request->tahun;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllInformationByKeywordInCtg($getCategory, $getKeyword);
            } else {
                return self::getAllInformationByCategorySlug($getCategory, $limit);
            }
        } elseif (!empty($getYear)) {
            if (!empty($getMonth)) {
                return self::getAllByYearAndMonth($getYear, $getMonth);
            } else {
                return self::getAllByYear($getYear);
            }
        } elseif (!empty($getMonth)) {
            return self::getAllByMonth($getMonth);
        } elseif (!empty($getSlug)) {
            return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllInformationByKeyword($getKeyword);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllInformations($limit);
        }

        // Switch=======================
        // switch (true) {
        //     case $getCategory !== null && $getCategory !== '""' && $getCategory !== "":
        //         if ($getKeyword !== null && $getKeyword !== '""' && $getKeyword !== "") {
        //             return self::getAllInformationByKeywordInCtg($getCategory, $getKeyword);
        //         }
        //         return self::getAllInformationByCategorySlug($getCategory);
        //     case $getYear !== null && $getYear !== '""' && $getYear !== "":
        //         if ($getMonth !== null && $getMonth !== '""' && $getMonth !== "") {
        //             return self::getAllByYearAndMonth($getYear, $getMonth);
        //         }
        //         return self::getAllByYear($getYear);
        //     case $getMonth !== null && $getMonth !== '""' && $getMonth !== "":
        //         return self::getAllByMonth($getMonth);
        //     case $getSlug !== null && $getSlug !== '""' && $getSlug !== "":
        //         return self::showBySlug($getSlug);
        //     case $getKeyword !== null && $getKeyword !== '""' && $getKeyword !== "":
        //         return self::getAllInformationByKeyword($getKeyword);
        //     case $getId !== null && $getId !== '""' && $getId !== "":
        //         return self::findById($getId);

        //     default:
        //         return self::getAllInformations($limit);
        // }
    }

    // getAll
    public function getAllInformations($limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key =  $this->generalRedisKeys . "public_All_" . request()->get("page", 1) . '_limit#' . $limit;
            $keyAuth =  $this->generalRedisKeys . "auth_All_" . request()->get("page", 1) . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Informasi from (CACHE)", $result);
            }

            $information = Information::with(['userId', 'editedBy', 'ctgInformation'])
                ->latest('created_at')
                ->paginate($limit);

            if ($information) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;
                    if ($item->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                        unset($item->file, $item->file_type, $item->file_size);
                    }

                    unset($item->userId, $item->editedBy, $item->ctgInformation);
                    return $item;
                }, $modifiedData);

                $key = $isAuthenticated ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($information));
                return $this->success("List keseluruhan Informasi", $information);
            }

            //public
            $keyAll = $key . "public_All_" . request()->get("page", 1) . '_limit#' . $limit;
            if (Redis::exists($keyAll)) {
                $result = json_decode(Redis::get($keyAll));
                return $this->success("List Keseluruhan Informasi from (CACHE)", $result);
            }
            $information = Information::latest('created_at')->paginate($limit);
            Redis::set($keyAll, json_encode($information));
            Redis::expire($keyAll, 60); // Cache for 60 seconds
            return $this->success("List kesuluruhan Informasi", $information);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //filterByCategory
    public function getAllInformationByCategorySlug($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("List Keseluruhan Informasi berdasarkan Kategori Informasi dengan slug = ($slug) from (CACHE)", $result);
            }
            $category = Ctg_Information::where('slug', $slug)->first();
            if ($category) {
                $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                    ->where('ctg_information_id', $category->id)
                    ->latest('created_at')
                    ->paginate($limit);

                // if ($information->total() > 0) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;
                    if ($item->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                        unset($item->file, $item->file_type, $item->file_size);
                    }

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan Kategori Informasi dengan slug = ($slug)", $information);
            } else {
                return $this->error("Not Found", "Informasi berdasarkan Kategori Informasi dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //searchByKeywords
    public function getAllInformationByKeyword($keyword)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            // $keyword = $keyword->keyword;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("List Informasi dengan keyword = ($keyword) from (CACHE)", $result);
            }

            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_informasi', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate(12);

            if ($information->total() > 0) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;
                    if ($item->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                        unset($item->file, $item->file_type, $item->file_size);
                    }

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan keyword = ($keyword)", $information);
            } else {
                return $this->error("Not Found", "Informasi dengan keyword = ($keyword) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //searchByKeywordsInCtg
    public function getAllInformationByKeywordInCtg($slug, $keyword)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            //$keyword = $keyword->keyword;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("List Informasi dengan keyword = ($keyword) dalam Kategori ($slug) from (CACHE)", $result);
            }

            $category = Ctg_Information::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->where('ctg_information_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_informasi', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate(12);

            // if ($information->total() > 0) {
            if ($information) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;
                    if ($item->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                        unset($item->file, $item->file_type, $item->file_size);
                    }

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $information);
            }
            return $this->error("Not Found", "Informasi dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // findOne
    public function showBySlug($slug)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("List Informasi berdasarkan slug = ($slug) from (CACHE)", $result);
            }

            $slug = Str::slug($slug);
            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->where('slug', 'LIKE', '%' . $slug . '%')
                ->latest('created_at')
                ->paginate(12);

            if ($information->total() > 0) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;
                    if ($item->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                        unset($item->file, $item->file_type, $item->file_size);
                    }

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($information));

                return $this->success("List Informasi berdasarkan slug = ($slug)", $information);
            } else {
                return $this->error("Not Found", "List Informasi berdasarkan Slug: ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // getByDate (month and year)
    public function getAllByYearAndMonth($year, $month)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            $cacheKey = $key . "year&month_" . $year . "_" . $month;

            if (Redis::exists($cacheKey)) {
                $result = json_decode(Redis::get($cacheKey));
                return $this->success("List Informasi berdasarkan bulan ($month) dan tahun ($year) from (CACHE)", $result);
            }

            if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                return $this->error("Invalid Input", "Tahun dan bulan yang dimasukkan tidak valid, pastikan tahun dalam format 4 digit (misalnya 2023) dan bulan dalam format 2 digit (misalnya 07)", 400);
            }

            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($information) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan bulan ($month) dan tahun ($year)", $information);
            } else {
                return $this->error("Not Found", "List Informasi berdasarkan bulan ($month) tahun ($year) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }


    // get by year
    public function getAllByYear($year)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            $cacheKey = $key . "year_" . $year;

            if (Redis::exists($cacheKey)) {
                $result = json_decode(Redis::get($cacheKey));
                return $this->success("List Informasi berdasarkan tahun ($year) from (CACHE)", $result);
            }
            if (!preg_match('/^\d{4}$/', $year)) {
                return $this->error("Invalid Input", "Tahun yang dimasukkan tidak valid, pastikan tahun dalam format 4 digit, misalnya (2023)", 400);
            }

            $startDate = "$year-01-01";
            $endDate = "$year-12-31";

            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($information) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan tahun ($year)", $information);
            } else {
                return $this->error("Not Found", "List Informasi berdasarkan tahun ($year) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // get by month
    public function getAllByMonth($month)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            $cacheKey = $key . "month_" . $month;

            if (Redis::exists($cacheKey)) {
                $result = json_decode(Redis::get($cacheKey));
                return $this->success("List Informasi berdasarkan bulan ($month) from (CACHE)", $result);
            }
            if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                return $this->error("Invalid Input", "Bulan yang dimasukkan tidak valid, pastikan bulan dalam format 2 digit, misalnya (07)", 400);
            }
            $currentYear = date('Y');
            $startDate = "$currentYear-$month-01";

            $endDate = date('Y-m-t', strtotime($startDate));

            $information = Information::with(['user', 'userId', 'editedBy', 'ctgInformation'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($information) {
                $modifiedData = $information->items();
                $modifiedData = array_map(function ($item) {
                    $item->user_id = optional($item->userId)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_information_id = optional($item->ctgInformation)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('FILE_URL') . $item->slug;

                    unset($item->userId, $item->editedBy, $item->ctgInformation, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($information));

                return $this->success("List Keseluruhan Informasi berdasarkan bulan ($month) tahun ($currentYear)", $information);
            } else {
                return $this->error("Not Found", "List Informasi berdasarkan bulan ($month) tahun ($currentYear) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("Detail Informasi dengan ID = ($id) from (CACHE)", $result);
            }

            $information = Information::find($id);
            if ($information) {
                $isAuthenticated = Auth::check();
                $createdBy = User::select('id', 'name')->find($information->user_id);
                $editedBy = User::select('id', 'name')->find($information->edited_by);
                $ctgInformation = Ctg_Information::select('id', 'title_category', 'slug')->find($information->ctg_information_id);

                $information->user_id = optional($createdBy)->only(['id', 'name']);
                $information->edited_by = optional($editedBy)->only(['id', 'name']);
                $information->ctg_information_id = optional($ctgInformation)->only(['id', 'title_category', 'slug']);
                $information->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                $information->url = env('FILE_URL') . $information->slug;
                if ($information->ctg_information_id['slug'] === 'informasi-terkecualikan' && !$isAuthenticated) {
                    unset($information->file, $information->file_type, $information->file_size);
                }

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($information));

                // Redis::set($key . $id, json_encode($information));
                // Redis::expire($key . $id, 60); // Cache for 1 minute

                return $this->success("Detail Informasi dengan ID ($id)", $information);
            } else {
                return $this->error("Not Found", "Detail Informasi dengan ID ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createInformation($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_informasi'       =>  'required',
                'posted_at'             =>  'required',
                'ctg_information_id'    =>  'required',
                'file'                  =>  'required|
                                            mimes:pdf|
                                            max:10240',
            ],
            [
                'information_title.required' => 'information_title tidak boleh kosong',
                'opd_penanggung_jawab.required' => 'opd_penanggung_jawab tidak boleh kosong',
                'posted_at.required' => 'posted_at tidak boleh kosong',
                'file.required' => 'File tidak boleh kosong',
                'ctg_information_id.required' => 'ctg_information_id tidak boleh kosong',
                'file.mimes' => 'Format File tidak didukung!,mohon inputkan File bertipe pdf',
                'file.max' => 'File terlalu besar, maksimal 10MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {
            $information = new Information();
            $information->title_informasi = $request->title_informasi;
            $information->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
            $information->description = $request->description;
            $information->sumber = $request->sumber;
            $information->isSync = true;
            $information->isNew = true;
            $information->slug =  Str::slug($request->title_informasi, '-');
            $information->posted_at = Carbon::createFromFormat('d-m-Y', $request->posted_at);

            $code = $this->infCode;
            $information->kode_informasi =  $code . Helper::generateCode(5, Information::class, "kode_informasi");

            $user = Auth::user();
            $information->user_id = $user->id;
            $information->edited_by = $user->id;

            $ctg_information_id = $request->ctg_information_id;
            $category = Ctg_Information::where('id', $ctg_information_id)->first();
            if ($category) {
                $information->ctg_information_id = $ctg_information_id;
            } else {
                return $this->error("Not Found", "Kategori Informasi dengan ID = ($ctg_information_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('file')) {
                $destination = 'public/files';
                $file = $request->file('file');
                $fileName = time() . "." . $file->getClientOriginalExtension();

                $information->file = $fileName;
                $information->file_type = $file->getClientOriginalExtension();

                $checkSize = $file->getSize();
                $fileSizeInMB = $checkSize / 1048576; // 1 MB = 1048576 byte
                $fileSizeInMB = round($fileSizeInMB, 2);
                $information->file_size = $fileSizeInMB . ' MB';

                //storeOriginal
                $file->storeAs($destination, $fileName);

                $information->url = env('FILE_URL') . $information->slug;

                // compress to thumbnail 
                // Helper::resizeImage($file, $fileName, $request);
            }

            // Simpan objek Information
            $create = $information->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Informasi Berhasil ditambahkan!", $information);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // update
    public function updateInformation($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_informasi'       =>  'required',
                'posted_at'             =>  'required',
                'ctg_information_id'    =>  'required',
                'file'                  =>  'mimes:pdf|
                                            max:10240',
            ],
            [
                'information_title.required' => 'information_title tidak boleh kosong',
                'opd_penanggung_jawab.required' => 'opd_penanggung_jawab tidak boleh kosong',
                'posted_at.required' => 'posted_at tidak boleh kosong',
                'ctg_information_id.required' => 'ctg_information_id tidak boleh kosong',
                'file.mimes' => 'Format File tidak didukung!,mohon inputkan File bertipe pdf',
                'file.max' => 'File terlalu besar, maksimal 10MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {
            // search
            $information = Information::find($id);

            // Check if the information exists
            if (!$information) {
                return $this->error("Not Found", "Informasi dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                // Checking Category_id
                $id = $request->ctg_information_id;
                $checkCategory = Ctg_Information::find($id);
                if (!$checkCategory) {
                    return $this->error("Not Found", "Kategori Informasi ID = ($id) tidak ditemukan!", 404);
                }

                // processing new image
                if ($request->hasFile('file')) {
                    if ($information->file) {
                        Storage::delete('public/files/' . $information->file);
                        // Storage::delete('public/thumbnails/t_images/' . $information->image);
                    }
                    $destination = 'public/files';
                    $file = $request->file('file');
                    $fileName = time() . "." . $file->getClientOriginalExtension();
                    // dd($fileName);

                    $information->file = $fileName;
                    $information->file_type = $file->getClientOriginalExtension();

                    $checkSize = $file->getSize();
                    $fileSizeInMB = $checkSize / 1048576; // 1 MB = 1048576 byte
                    $fileSizeInMB = round($fileSizeInMB, 2);
                    $information->file_size = $fileSizeInMB . ' MB';
                    //storeOriginal
                    $file->storeAs($destination, $fileName);
                    $information['slug'] = Str::slug($request->title_informasi, '-');
                    $information->url = env('FILE_URL') . $information->slug;

                    //compressImage
                    // Helper::resizeImage($file, $fileName, $request);
                }
                $information['slug'] = Str::slug($request->title_informasi, '-');
                $information->url = env('FILE_URL') . $information->slug;
                $information->file = $information->file;
            }
            // approved
            $information['title_informasi'] = $request->title_informasi;
            $information['description'] = $request->description;
            $information['opd_penanggung_jawab'] =  Setting::pluck('name_dinas')->implode(', ');
            $information['sumber'] = $request->sumber;
            $information['posted_at'] = Carbon::createFromFormat('d-m-Y', $request->posted_at);
            $information['ctg_information_id'] = $request->ctg_information_id;
            $information['isSync'] = false;
            $information['isNew'] = true;
            $information['kode_informasi'] =  $information->kode_informasi;
            $oldCreatedBy = $information->user_id;
            $information['user_id'] = $oldCreatedBy;
            $information['edited_by'] = Auth::user()->id;

            $update = $information->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Informasi Berhasil diperbaharui!", $information);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteInformation($id)
    {
        try {
            // search
            $information = Information::find($id);
            // return dd($information);
            if (!$information) {
                return $this->error("Not Found", "Informasi dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($information->file) {
                Storage::delete('public/files/' . $information->file);
                // Storage::delete('public/thumbnails/t_files/' . $information->file);
            }

            $del = $information->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED!", "Informasi dengan ID = ($id) Berhasil dihapus!");
            }
            // approved
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
