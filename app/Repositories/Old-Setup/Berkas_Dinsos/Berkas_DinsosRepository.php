<?php

namespace App\Repositories\Berkas_Dinsos;

use App\Repositories\Berkas_Dinsos\Berkas_DinsosInterface as Berkas_DinsosInterface;
use App\Models\Berkas_Dinsos;
use App\Models\Ctg_Berkas;
use App\Models\User;
use App\Models\Setting;
use App\Traits\API_response;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;


class Berkas_DinsosRepository implements Berkas_DinsosInterface
{

    protected $berkas_dinsos;
    protected $generalRedisKeys;
    protected $DocDinsosCode;

    // Response API HANDLER
    use API_response;

    public function __construct(Berkas_Dinsos $berkas_dinsos)
    {
        $this->berkas_dinsos = $berkas_dinsos;
        $this->generalRedisKeys = "berkas_dinsos_";
        $this->DocDinsosCode = "dinsos-";
    }

    public function getBerkas_Dinsos($request)
    {
        if (($request->order != null) or ($request->order != "")) {
            $order = $request->order == "desc" ? "desc" : "asc";
        } else {
            $order = "desc";
        }
        $limit = Helper::limitDatas($request);
        $getKeyword = $request->search;
        $getSlug = $request->slug;
        $getId = $request->id;
        $getCategory = $request->ctg;
        $getMonth = $request->bulan;
        $getYear = $request->tahun;
        $getTrash = $request->trash;
        $getRestore = $request->restore_all;
        $getRestoreId = $request->restore_id;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllBerkasByKeywordInCtg($getCategory, $getKeyword, $order);
            }
            if (!empty($getMonth and $getYear)) {
                return self::getAllBerkasInCtgByYearAndMonth($getCategory, $getYear, $getMonth);
            } else {
                return self::getAllBerkasByCategorySlug($getCategory, $limit, $order);
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
            return self::getAllBerkas_DinsosByKeyword($getKeyword, $limit);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } elseif (!empty($getTrash)) {
            return self::getAllTrashBerkas_Dinsos($order, $limit);
        } elseif (!empty($getRestore)) {
            return self::restore($getRestore);
        } elseif (!empty($getRestoreId)) {
            return self::restoreById($getRestoreId);
        } else {
            return self::getAllBerkas_Dinsos($limit, $order);
        }
    }

    // getAll
    public function getAllBerkas_Dinsos($limit, $order)
    {
        try {
            $isAuthenticated = Auth::check();
            $page = request()->get("page", 1);
            $key =  $this->generalRedisKeys . "public_All_" . $page . '_limit#' . $limit . $order;
            $keyAuth =  $this->generalRedisKeys . "auth_All_" . $page . '_limit#' . $limit . $order;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Berkas from (CACHE)", $result);
            }

            $berkas_dinsos = Berkas_Dinsos::with(['createdBy', 'editedBy'])
                ->orderBy('posted_at', $order)
                ->paginate($limit);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = $isAuthenticated ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($berkas_dinsos));
                return $this->success("List keseluruhan Berkas", $berkas_dinsos);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllBerkasByKeywordInCtg($slug, $keyword, $order)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . $order;
            //$keyword = $keyword->keyword;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("List Berkas dengan keyword = ($keyword) dalam Kategori ($slug) from (CACHE)", $result);
            }

            $category = Ctg_Berkas::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori Berkas dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $berkas = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy', 'ctgBerkas'])
                ->where('ctg_berkas_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_berkas', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->orderBy('posted_at', $order)
                ->paginate(12);

            // if ($berkas->total() > 0) {
            if ($berkas) {
                $modifiedData = $berkas->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_berkas_id = optional($item->ctgBerkas)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;
                    // if ($item->ctg_berkas_id['slug'] === 'berkas-terkecualikan' && !$isAuthenticated) {
                    //     unset($item->file, $item->file_type, $item->file_size);
                    // }

                    unset($item->createdBy, $item->editedBy, $item->ctgBerkas, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($berkas));

                return $this->success("List Keseluruhan Berkas berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $berkas);
            }
            return $this->error("Not Found", "Berkas dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllBerkasByCategorySlug($slug, $limit, $order)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit . $order;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("List Keseluruhan Berkas berdasarkan Kategori Berkas dengan slug = ($slug) from (CACHE)", $result);
            }
            $category = Ctg_Berkas::where('slug', $slug)->first();
            if ($category) {
                $berkas = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy', 'ctgBerkas'])
                    ->where('ctg_berkas_id', $category->id)
                    ->orderBy('posted_at', $order)
                    ->paginate($limit);

                // if ($berkas->total() > 0) {
                $modifiedData = $berkas->items();
                $modifiedData = array_map(function ($item) {
                    $isAuthenticated = Auth::check();
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_berkas_id = optional($item->ctgBerkas)->only(['id', 'title_category', 'slug']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;
                    // if ($item->ctg_berkas_id['slug'] === 'berkas-terkecualikan' && !$isAuthenticated) {
                    //     unset($item->file, $item->file_type, $item->file_size);
                    // }

                    unset($item->createdBy, $item->editedBy, $item->ctgBerkas, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($berkas));

                return $this->success("List Keseluruhan Berkas berdasarkan Kategori Berkas dengan slug = ($slug)", $berkas);
            } else {
                return $this->error("Not Found", "Berkas berdasarkan Kategori Berkas dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // getAll
    public function getAllTrashBerkas_Dinsos($order, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . "#limit" . $limit . "#order" . $order;
            $keyAuth = $this->generalRedisKeys . "auth_" . "#limit" . $limit . "#order" . $order;
            $key = $isAuthenticated ? $keyAuth : $key;

            $keyAll = $key . "All_Trash_" . request()->get("page", 1);
            if (Redis::exists($keyAll)) {
                $result = json_decode(Redis::get($keyAll));
                return $this->success("List Keseluruhan Berkas_Dinsos Trash from (CACHE)", $result);
            }
            $berkas_dinsos = Berkas_Dinsos::onlyTrashed()->with(['createdBy', 'editedBy'])
                ->orderBy('posted_at', $order)
                ->paginate($limit);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = $isAuthenticated ? $keyAll : $keyAll;
                Redis::setex($key, 60, json_encode($berkas_dinsos));

                return $this->success("List keseluruhan Berkas_Dinsos <Trash>", $berkas_dinsos);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
    public function getAllBerkasInCtgByYearAndMonth($slug, $year, $month)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            $cacheKey = $key . $slug .  "year&month_" . $year . "_" . $month;

            if (Redis::exists($cacheKey)) {
                $result = json_decode(Redis::get($cacheKey));
                return $this->success("List Berkas dalam Kategori ($slug) bulan ($month) dan tahun ($year) from (CACHE)", $result);
            }

            if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                return $this->error("Invalid Input", "Tahun dan bulan yang dimasukkan tidak valid, pastikan tahun dalam format 4 digit (misalnya 2023) dan bulan dalam format 2 digit (misalnya 07)", 400);
            }

            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            $category = Ctg_Berkas::where('slug', $slug)->first();
            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->where('ctg_berkas_id', $category->id)
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($berkas_dinsos));

                return $this->success("List Keseluruhan Berkas dalam Kategori ($slug) berdasarkan bulan ($month) dan tahun ($year)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "List Berkas dalam Kategori ($slug) berdasarkan bulan ($month) tahun ($year) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
    //searchByKeywords
    public function getAllBerkas_DinsosByKeyword($keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("List Berkas dengan keyword = ($keyword) from (CACHE)", $result);
            }

            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_berkas', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            if ($berkas_dinsos->total() > 0) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($berkas_dinsos));

                return $this->success("List Keseluruhan Berkas berdasarkan keyword = ($keyword)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "Berkas dengan keyword = ($keyword) tidak ditemukan!", 404);
            }
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
                return $this->success("List Berkas berdasarkan slug = ($slug) from (CACHE)", $result);
            }

            $slug = Str::slug($slug);
            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->where('slug', 'LIKE', '%' . $slug . '%')
                ->latest('created_at')
                ->paginate(12);

            if ($berkas_dinsos->total() > 0) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($berkas_dinsos));

                return $this->success("List Berkas berdasarkan slug = ($slug)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "List Berkas berdasarkan slug: ($slug) tidak ditemukan!", 404);
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
                return $this->success("List Berkas berdasarkan bulan ($month) dan tahun ($year) from (CACHE)", $result);
            }

            if (!preg_match('/^\d{4}$/', $year) || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                return $this->error("Invalid Input", "Tahun dan bulan yang dimasukkan tidak valid, pastikan tahun dalam format 4 digit (misalnya 2023) dan bulan dalam format 2 digit (misalnya 07)", 400);
            }

            $startDate = "$year-$month-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($berkas_dinsos));

                return $this->success("List Keseluruhan Berkas berdasarkan bulan ($month) dan tahun ($year)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "List Berkas berdasarkan bulan ($month) tahun ($year) tidak ditemukan!", 404);
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
                return $this->success("List Berkas berdasarkan tahun ($year) from (CACHE)", $result);
            }
            if (!preg_match('/^\d{4}$/', $year)) {
                return $this->error("Invalid Input", "Tahun yang dimasukkan tidak valid, pastikan tahun dalam format 4 digit, misalnya (2023)", 400);
            }

            $startDate = "$year-01-01";
            $endDate = "$year-12-31";

            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($berkas_dinsos));

                return $this->success("List Keseluruhan Berkas berdasarkan tahun ($year)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "List Berkas berdasarkan tahun ($year) tidak ditemukan!", 404);
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
                return $this->success("List Berkas berdasarkan bulan ($month) from (CACHE)", $result);
            }
            if (!preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
                return $this->error("Invalid Input", "Bulan yang dimasukkan tidak valid, pastikan bulan dalam format 2 digit, misalnya (07)", 400);
            }
            $currentYear = date('Y');
            $startDate = "$currentYear-$month-01";

            $endDate = date('Y-m-t', strtotime($startDate));

            $berkas_dinsos = Berkas_Dinsos::with(['user', 'createdBy', 'editedBy'])
                ->whereBetween('posted_at', [$startDate, $endDate])
                ->latest('created_at')
                ->paginate(12);

            if ($berkas_dinsos) {
                $modifiedData = $berkas_dinsos->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                    $item->url = env('BERKAS_DINSOS') . $item->slug;

                    unset($item->createdBy, $item->editedBy, $item->user);
                    return $item;
                }, $modifiedData);

                Redis::setex($cacheKey, 60, json_encode($berkas_dinsos));

                return $this->success("List Keseluruhan Berkas berdasarkan bulan ($month) tahun ($currentYear)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "List Berkas berdasarkan bulan ($month) tahun ($currentYear) tidak ditemukan!", 404);
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
                return $this->success("Detail Berkas dengan ID = ($id) from (CACHE)", $result);
            }

            $berkas_dinsos = Berkas_Dinsos::find($id);
            if ($berkas_dinsos) {
                $createdBy = User::select('id', 'name')->find($berkas_dinsos->created_by);
                $editedBy = User::select('id', 'name')->find($berkas_dinsos->edited_by);

                $berkas_dinsos->created_by = optional($createdBy)->only(['id', 'name']);
                $berkas_dinsos->edited_by = optional($editedBy)->only(['id', 'name']);
                $berkas_dinsos->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
                $berkas_dinsos->url = env('BERKAS_DINSOS') . $berkas_dinsos->slug;

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($berkas_dinsos));
                return $this->success("Detail Berkas dengan ID ($id)", $berkas_dinsos);
            } else {
                return $this->error("Not Found", "Detail Berkas dengan ID ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createBerkas_Dinsos($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_berkas'       =>  'required',
                'ctg_berkas_id'      =>  'required',
                'file'                  =>  'required|
                                            mimes:pdf|
                                            max:10240',
            ],
            [
                'title_berkas.required' => 'Title tidak boleh kosong',
                'ctg_berkas_id.required' => 'Kategori Berkas tidak boleh kosong',
                'file.required' => 'File tidak boleh kosong',
                'file.mimes' => 'Format File tidak didukung!,mohon inputkan File bertipe pdf',
                'file.max' => 'File terlalu besar, maksimal 10MB',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {
            $berkas_dinsos = new Berkas_Dinsos();
            $berkas_dinsos->title_berkas = $request->title_berkas;
            $berkas_dinsos->opd_penanggung_jawab = Setting::pluck('name_dinas')->implode(', ');
            $berkas_dinsos->description = $request->description;
            $berkas_dinsos->sumber = $request->sumber ?? '';
            $berkas_dinsos->slug =  Str::slug($request->title_berkas, '-');
            $berkas_dinsos->posted_at = Carbon::createFromFormat('d-m-Y', $request->posted_at);

            $code = $this->DocDinsosCode;
            $berkas_dinsos->kode_berkas =  $code . Helper::generateCode(5, Berkas_Dinsos::class, "kode_berkas");

            $user = Auth::user();
            $berkas_dinsos->created_by = $user->id;
            $berkas_dinsos->edited_by = $user->id;

            $ctg_berkas_id = $request->ctg_berkas_id;
            $category = Ctg_Berkas::where('id', $ctg_berkas_id)->first();
            if ($category) {
                $berkas_dinsos->ctg_berkas_id = $ctg_berkas_id;
            } else {
                return $this->error("Not Found", "Kategori Berkas dengan ID = ($ctg_berkas_id) tidak ditemukan!", 404);
            }

            if ($request->hasFile('file')) {
                $destination = 'public/files/dinsos';
                $file = $request->file('file');
                $fileName = time() . "." . $file->getClientOriginalExtension();

                $berkas_dinsos->file = $fileName;
                $berkas_dinsos->file_type = $file->getClientOriginalExtension();

                $checkSize = $file->getSize();
                $fileSizeInMB = $checkSize / 1048576; // 1 MB = 1048576 byte
                $fileSizeInMB = round($fileSizeInMB, 2);
                $berkas_dinsos->file_size = $fileSizeInMB . ' MB';

                //storeOriginal
                $file->storeAs($destination, $fileName);

                $berkas_dinsos->url = env('BERKAS_DINSOS') . $berkas_dinsos->slug;
            }

            // Simpan objek Berkas_Dinsos
            $create = $berkas_dinsos->save();

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Berkas Berhasil ditambahkan!", $berkas_dinsos);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // update
    public function updateBerkas_Dinsos($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_berkas'       =>  'required',
                'ctg_berkas_id'      =>  'required',
                'file'                  =>  'mimes:pdf|
                                            max:10240',
            ],
            [
                'title_berkas.required' => 'title_berkas tidak boleh kosong',
                'ctg_berkas_id.required' => 'Kategori Berkas tidak boleh kosong',
                'file.required' => 'File tidak boleh kosong',
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
            $berkas_dinsos = Berkas_Dinsos::find($id);
            if (!$berkas_dinsos) {
                return $this->error("Not Found", "Berkas dengan ID = ($id) tidak ditemukan!", 404);
            } else {
                $id = $request->ctg_berkas_id;
                $checkCategory = Ctg_Berkas::find($id);
                if (!$checkCategory) {
                    return $this->error("Not Found", "Kategori Berkas dengan ID = ($id) tidak ditemukan!", 404);
                }

                if ($request->hasFile('file')) {
                    if ($berkas_dinsos->file) {
                        Storage::delete('public/files/dinsos/' . $berkas_dinsos->file);
                    }
                    $destination = 'public/files/dinsos';
                    $file = $request->file('file');
                    $fileName = time() . "." . $file->getClientOriginalExtension();

                    $berkas_dinsos->file = $fileName;
                    $berkas_dinsos->file_type = $file->getClientOriginalExtension();

                    $checkSize = $file->getSize();
                    $fileSizeInMB = $checkSize / 1048576; // 1 MB = 1048576 byte
                    $fileSizeInMB = round($fileSizeInMB, 2);
                    $berkas_dinsos->file_size = $fileSizeInMB . ' MB';
                    //storeOriginal
                    $file->storeAs($destination, $fileName);

                    $berkas_dinsos['slug'] = Str::slug($request->title_berkas, '-');
                    $berkas_dinsos->url = env('BERKAS_DINSOS') . $berkas_dinsos->slug;
                }
                $berkas_dinsos['slug'] = Str::slug($request->title_berkas, '-');
                $berkas_dinsos->url = env('BERKAS_DINSOS') . $berkas_dinsos->slug;
                $berkas_dinsos->file = $berkas_dinsos->file;
            }
            // approved
            $berkas_dinsos['title_berkas'] = $request->title_berkas;
            $berkas_dinsos['description'] = $request->description;
            $berkas_dinsos['opd_penanggung_jawab'] =  Setting::pluck('name_dinas')->implode(', ');
            $berkas_dinsos['ctg_berkas_id'] = $request->ctg_berkas_id;
            $berkas_dinsos['sumber'] = $request->sumber;
            $berkas_dinsos['posted_at'] = Carbon::createFromFormat('d-m-Y', $request->posted_at);
            $berkas_dinsos['kode_berkas'] =  $berkas_dinsos->kode_berkas;

            $oldCreatedBy = $berkas_dinsos->created_by;
            $berkas_dinsos['created_by'] = $oldCreatedBy;
            $berkas_dinsos['edited_by'] = Auth::user()->id;

            $update = $berkas_dinsos->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Berkas Berhasil diperbaharui!", $berkas_dinsos);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteBerkas_Dinsos($id)
    {
        try {

            // search
            $berkas_dinsos = Berkas_Dinsos::find($id);
            if (!$berkas_dinsos) {
                return $this->error("Not Found", "Berkas dengan ID = ($id) tidak ditemukan!", 404);
            }

            $del = $berkas_dinsos->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED!", "Berkas dengan ID = ($id) Berhasil dihapus!");
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
            $berkas_dinsos = Berkas_Dinsos::onlyTrashed()->find($id);
            if (!$berkas_dinsos) {
                return $this->error("Not Found", "Berkas_Dinsos dengan ID = ($id) tidak ditemukan di Trash!", 404);
            }

            $del = $berkas_dinsos->forceDelete();
            if ($del) {
                if ($berkas_dinsos->file) {
                    Storage::delete('public/files/dinsos/' . $berkas_dinsos->file);
                }
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED!", "Berkas_Dinsos dengan ID = ($id) Berhasil dihapus permanent!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Berkas_Dinsos::onlyTrashed();
            if ($data->restore()) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Restore Berkas_Dinsos Berhasil!");
            }
            return $this->error("FAILED", "Restore Berkas_Dinsos Gagal!", 400);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Berkas_Dinsos::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Restore Berkas_Dinsos dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Berkas_Dinsos dengan ID = ($id) Gagal!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
