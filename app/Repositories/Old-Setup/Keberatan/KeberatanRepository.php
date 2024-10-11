<?php

namespace App\Repositories\Keberatan;

use App\Helpers\Helper;
use App\Models\Keberatan;
use App\Repositories\Keberatan\KeberatanInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class KeberatanRepository implements KeberatanInterface
{
    private $keberatan;
    // 1 Minute redis expired
    private $expired = 100;
    private $nameKeyRedis = 'Keberatan-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Keberatan $keberatan)
    {
        $this->keberatan = $keberatan;
    }

    // public function getKeberatan1($request)
    // {
    //     $limit = Helper::limitDatas($request);

    //     if (($request->order != null) or ($request->order != "")) {
    //         $order = $request->order == "desc" ? "desc" : "asc";
    //     } else {
    //         $order = "desc";
    //     }
    //     $getSearch = $request->search;
    //     $getById = $request->id;
    //     $getTrash = $request->trash;
    //     $getRestore = $request->restore;
    //     $getRestoreId = $request->restoreid;

    //     if (($getSearch !== null) and ($getSearch !== '""') and ($getSearch !== "")) {
    //         return self::search($getSearch, $order, $limit);
    //     } else if (($getById !== null) and ($getById !== '""') and ($getById !== "")) {
    //         return self::getById($getById);
    //     } else if (($getTrash !== null) and ($getTrash !== '""') and ($getTrash !== "")) {
    //         return self::getAllTrash($order, $limit);
    //     } else if (($getRestore !== null) and ($getRestore !== '""') and ($getRestore !== "")) {
    //         return self::restore();
    //     } else if (($getRestoreId !== null) and ($getRestoreId !== '""') and ($getRestoreId !== "")) {
    //         return self::restoreById($getRestoreId);
    //     } else {
    //         // return self::getAll($order, $limit);
    //     }
    //     // }
    // }

    public function getKeberatan($request)
    {
        try {

            $limit = Helper::limitDatas($request);

            if (($request->order != null) or ($request->order != "")) {
                $order = $request->order == "desc" ? "desc" : "asc";
            } else {
                $order = "desc";
            }
            $getSearch = $request->search;
            $getById = $request->id;
            $getByIdentitas = $request->identitas;
            $getTrash = $request->trash;
            $getRestore = $request->restore;
            $getRestoreId = $request->restoreid;
            $paginate = $request->paginate;
            $params = $getSearch . $getById . $getByIdentitas . $getTrash . $getRestore . $getRestoreId . $limit . $order . $paginate;

            $nameLogin = self::checkLogin();
            $keyAll = $this->nameKeyRedis . "All-" . $nameLogin . request()->get('page', 1) . "#params" . $params;

            if (Redis::exists($keyAll)) {
                $result = json_decode(Redis::get($keyAll));
                return $this->success("List Keberatan from (CACHE)", $result);
            }

            if (Auth::check() && $request->filled('restore')) {
                return self::restore();
            }

            if (Auth::check() && $request->filled('restoreid')) {
                return self::restoreById($getRestoreId);
            }

            if (Auth::check() && $request->filled('trash')) {
                return self::getAllTrash($request, $order, $limit);
            }

            $query = Keberatan::orderBy('created_at', $order);


            if ($request->filled('search')) {
                $query->where('no_identitas', $getSearch);
                // ->orWhere('nama', 'like', "%$getSearch%");
            }
            if ($request->filled('id')) {
                $query->where('id', $getById);
            }

            if ($request->filled('identitas')) {
                $query->where('identitas', $getByIdentitas);
            }

            if ($paginate == "true") {
                $result = $query->paginate($limit);
                $paginateSet = true;
            } else {
                $result = $query->take($limit)->get();
                $paginateSet = false;
            }


            $datas = self::queryGetModify($result, true, $paginateSet);
            if (!Auth::check() and $result) {
                $hidden = ['id', 'created_by', 'edited_by'];
                $datas->makeHidden($hidden);
            }
            Redis::set($keyAll, json_encode($datas));
            Redis::expire($keyAll,  $this->expired); // Cache for 60 seconds

            return $this->success("List kesuluruhan Keberatan", $datas);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    function getAllTrash($request, $order, $limit)
    {
        try {
            $getSearch = $request->search;
            $getById = $request->id;
            $getByIdentitas = $request->identitas;
            $getTrash = $request->trash;
            $getRestore = $request->restore;
            $getRestoreId = $request->restoreid;
            $paginate = $request->paginate;
            $params = $getSearch . $getById . $getByIdentitas . $getTrash . $getRestore . $getRestoreId . $limit . $order . $paginate;

            $keyOne = $this->nameKeyRedis . "getAllTrash" . request()->get('page', 1) .  "#params" . $params;
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data Trash Keberatan  from (CACHE)", $result);
            }

            $query = Keberatan::onlyTrashed()->orderBy('created_at', $order);


            if ($request->filled('search')) {
                $query->where('no_identitas', 'like', "%$getSearch%")
                    ->orWhere('nama', 'like', "%$getSearch%");
            }
            if ($request->filled('id')) {
                $query->where('id', $getById);
            }

            if ($request->filled('identitas')) {
                $query->where('identitas', $getByIdentitas);
            }

            if ($paginate == "true") {
                $result = $query->paginate($limit);
                $paginateSet = true;
            } else {
                $result = $query->take($limit)->get();
                $paginateSet = false;
            }

            $datas = self::queryGetModify($result, true, $paginateSet);


            // $datas = Keberatan::onlyTrashed()->latest()->paginate($limit);
            // $data = Self::queryGetModify($datas, true);

            Redis::set($keyOne, json_encode($datas));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data Trash Keberatan", $datas);

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

    // function getAllBy($filter, $order, $limit)
    // {

    //     try {

    //         $nameLogin = self::checkLogin();
    //         $search = $filter == "top" ? 'views'  : 'posted_at';
    //         // $hidden = ['id', 'category_id', 'user_id'];

    //         $keyAll = $this->nameKeyRedis . "getAllBy-" . $nameLogin . Str::slug($filter) . request()->get('page', 1) . "#limit" . $limit . "#order" . $order;
    //         if (Redis::exists($keyAll)) {
    //             $result = json_decode(Redis::get($keyAll));
    //             return $this->success("List Keberatan berdasarkan populer from (CACHE)", $result);
    //         }

    //         $datas = Keberatan::orderBy($search, $order)->paginate($limit);
    //         $berita = self::queryGetModify($datas, true);
    //         if (!Auth::check() and $datas) {
    //             $hidden = ['id'];
    //             $berita->makeHidden($hidden);
    //         }
    //         Redis::set($keyAll, json_encode($berita));
    //         Redis::expire($keyAll,  $this->expired); // Cache for 60 seconds
    //         return $this->success("List kesuluruhan Keberatan", $berita);

    //         // $hidden = ['category_id', 'user_id'];


    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error!", $e->getMessage());
    //     }
    // }



    // findOne
    // function getById($id)
    // {
    //     try {
    //         $nameLogin = self::checkLogin();
    //         $keyOne = $this->nameKeyRedis . "getById-" . $nameLogin . Str::slug($id);
    //         if (Redis::exists($keyOne)) {
    //             $result = json_decode(Redis::get($keyOne));
    //             return $this->success("Keberatan dengan ID = ($id)  from (CACHE)", $result);
    //         }
    //         $data = Keberatan::find($id);
    //         if ($data) {
    //             // $datas = $this->query()->where('id', $id)->get();
    //             $berita = self::queryGetModify($data, false);
    //             if (!Auth::check()) {
    //                 $hidden = ['id'];
    //                 $berita->makeHidden($hidden);
    //             }
    //             Redis::set($keyOne, json_encode($berita));
    //             Redis::expire($keyOne,  $this->expired); // Cache for 60 seconds
    //             return $this->success("Keberatan dengan ID = ($id)", $berita);
    //         }
    //         return $this->error("Not Found", "Keberatan dengan ID = ($id) tidak ditemukan!", 404);
    //         // $data = $this->query()->where('keberatan.id', $id)->get();
    //         // return $this->success("Detail Keberatan", $data);
    //         // $hidden = ['id', 'category_id', 'user_id'];

    //         // if (Auth::check()) {
    //         //     $hidden = ['category_id', 'user_id'];
    //         // }
    //         // $data->makeHidden($hidden);

    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error!", $e->getMessage());
    //     }
    // }

    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'nama'     => 'required',
            'identitas'     => 'required',
            'no_identitas'  => 'required',
            'scan_identitas'           => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'informasi_diminta' => 'required',
            'alasan' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $fileName = $request->hasFile('scan_identitas') ? "keberatan_" . time() . "." . $request->scan_identitas->getClientOriginalExtension() : "";
            $data = [
                'nama' => $request->nama,
                'identitas' => $request->identitas,
                'no_identitas' => $request->no_identitas,
                'scan_identitas' => $fileName,
                'informasi_diminta' => $request->informasi_diminta,
                'alasan' => $request->alasan,
                'keterangan' => $request->keterangan,
                'catatan' => $request->catatan,
                'created_by' => Auth::check() ? Auth::user()->id : "",

            ];
            // Create Keberatan
            $add = Keberatan::create($data);

            if ($add) {
                Helper::saveImage('scan_identitas', $fileName, $request, $this->destinationImage);

                // delete Redis when insert data
                Helper::deleteRedis($this->nameKeyRedis . "*");

                return $this->success("Keberatan Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Keberatan gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama'     => 'required',
            'identitas'     => 'required',
            'no_identitas'  => 'required',
            'scan_identitas'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'informasi_diminta' => 'required',
            'alasan' => 'required',
            'status' => 'required'

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {
            // search
            $datas = Keberatan::find($id);
            // return $datas;
            // check
            if (!$datas) {
                return $this->error("Not Found", "Keberatan dengan ID = ($id) tidak ditemukan!", 404);
            }
            $datas['nama'] = $request->nama;
            $datas['identitas'] = $request->identitas;
            $datas['no_identitas'] = $request->no_identitas;
            $datas['informasi_diminta'] = $request->informasi_diminta;
            $datas['alasan'] = $request->alasan;
            $datas['keterangan'] = $request->keterangan;
            $datas['catatan'] = $request->catatan;
            $datas['status'] = $request->status;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('scan_identitas')) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->scan_identitas);


                // Image name
                $fileName = 'keberatan_' . time() . "." . $request->scan_identitas->getClientOriginalExtension();
                $datas['scan_identitas'] = $fileName;
                // Image save in public folder
                Helper::saveImage('scan_identitas', $fileName, $request, $this->destinationImage);
            } else {

                // if ($request->delete_scan_identitas) {
                //     // Old image delete
                //     Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->scan_identitas);

                //     $datas['scan_identitas'] = null;
                // }
                $datas['scan_identitas'] = $datas->scan_identitas;
            }
            // update datas
            if ($datas->save()) {
                // delete Redis when insert data
                Helper::deleteRedis($this->nameKeyRedis . "*");

                return $this->success("Keberatan Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Keberatan gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Keberatan::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Keberatan dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            if ($data->delete()) {
                Helper::deleteRedis($this->nameKeyRedis . "*");
                return $this->success("COMPLETED", "Keberatan dengan ID = ($id) Berhasil dihapus!");
            }

            return $this->error("FAILED", "Keberatan dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {

            $data = Keberatan::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "Keberatan dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old image delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->scan_identitas);

                Helper::deleteRedis($this->nameKeyRedis . "*");
                return $this->success("COMPLETED", "Keberatan dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "Keberatan dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Keberatan::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->nameKeyRedis . "*");
                return $this->success("COMPLETED", "Restore Keberatan Berhasil!");
            }
            return $this->error("FAILED", "Restore Keberatan Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Keberatan::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->nameKeyRedis . "*");
                return $this->success("COMPLETED", "Restore Keberatan dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Keberatan dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }



    // function search($keyword, $order, $limit)
    // {
    //     try {
    //         $nameLogin = self::checkLogin();
    //         $keyOne = $this->nameKeyRedis . "search-" . $nameLogin . Str::slug($keyword) .  request()->get('page', 1) . "#limit" . $limit . "#order" . $order;
    //         if (Redis::exists($keyOne)) {
    //             $result = json_decode(Redis::get($keyOne));
    //             return $this->success("Keberatan dengan Keyword = ($keyword)  from (CACHE)", $result);
    //         }
    //         $datas = Keberatan::orderBy('posted_at', $order)->where('berita_title', 'LIKE', '%' . $keyword . '%')->paginate($limit);
    //         $berita = self::queryGetModify($datas, true);
    //         if (!empty($berita)) {
    //             if (!Auth::check()) {
    //                 $hidden = ['id'];
    //                 $berita->makeHidden($hidden);
    //             }
    //             Redis::set($keyOne, json_encode($berita));
    //             Redis::expire($keyOne,  $this->expired); // Cache for 60 seconds
    //             return $this->success("Keberatan By Keyword = ($keyword)", $berita);
    //         }
    //         return $this->error("Not Found", "Keberatan dengan keyword = ($keyword) tidak ditemukan!", 404);
    //     } catch (Exception $e) {
    //         // return $this->error($e->getMessage(), $e->getCode());
    //         return $this->error("Internal Server Error!", $e->getMessage());
    //     }
    //     // $berita = $this->query()->where('berita_title', 'LIKE', '%' . $keyword . '%')->get();
    //     // // return $berita;
    //     // if (empty($berita)) {
    //     //     return $this->error("Keberatan dengan Keyword = ($keyword) tidak ditemukan!", 404);
    //     // }
    //     // $hidden = ['id', 'category_id', 'user_id'];
    //     // if (Auth::check()) {
    //     //     $hidden = ['category_id', 'user_id'];
    //     // }
    //     // $berita->makeHidden($hidden);

    //     // $berita['keyword'] = $keyword;

    //     // return $this->success("Search Keberatan", $berita);
    // }







    function queryGetModify($datas, $manyResult = false, $paginate = true)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');
                // return $modifiedData;
                $modifiedData = array_map(function ($item) {
                    $item->alasan = json_decode($item->alasan);
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
                self::modifyData($datas);
            }
            return $datas;
        }
    }

    function modifyData($item)
    {

        // $item->scan_identitas = Helper::convertImageToBase64('images/', $item->scan_identitas);
        $item = Helper::queryGetUserModify($item);
        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }
}
