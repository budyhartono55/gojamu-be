<?php

namespace App\Repositories\Wilayah\Peliuk;


use App\Models\Wilayah\Peliuk;
use App\Repositories\Wilayah\Peliuk\PeliukInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\PeliukImport;
use App\Models\Wilayah\Desa;
use Maatwebsite\Excel\Facades\Excel;

class PeliukRepository implements PeliukInterface
{
    private $peliuk;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "peliuk_";
    use API_response;

    public function __construct(Peliuk $peliuk)
    {
        $this->peliuk = $peliuk;
    }

    public function getPeliuk($request)
    {

        try {
            $findById = $request->id;
            $findByDesa = $request->desa;
            $findByRW = $request->rw;
            $findByRT = $request->rt;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? "asc" : "desc";
            $params = $findById . $findByDesa . $findByRW . $findByRT . $paginate . $order;
            $desa = Desa::find($findByDesa);
            if (!$desa) {
                return $this->success("NOT FOUND", "ID Desa ({$findByDesa}) Tidak Ditemukan", 404);
            }

            $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;

            $desaName = Desa::select('nama')->where('id', $findByDesa)->first()->nama;


            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Peliuk Berdasarkan Desa {$desaName} from (CACHE)", $result);
            }

            $query = Peliuk::orderBy('id', $order);


            if ($request->filled('desa')) {
                $query->where('id', 'LIKE', "{$findByDesa}1%");
            }

            if ($request->filled('rw')) {
                $query->where('rw',  'like', "%{$findByRW}%");
            }
            if ($request->filled('rt')) {
                $query->where('rt',  'like', "%{$findByRT}%");
            }
            if ($request->filled('id')) {
                $query->where('id', $findById);
            }
            if ($paginate == "true") {
                $result = $query->paginate(10);
                $paginateSet = true;
            } else {
                $result = $query->get();
                $paginateSet = false;
            }

            $datas = self::queryGetModify($result, true, $paginateSet);
            // if (!Auth::check() and $result) {
            //     $hidden = ['id', 'catatan', 'created_by', 'edited_by'];
            //     $datas->makeHidden($hidden);
            // }

            // noArray

            if ($datas) {
                Redis::set($key, json_encode($datas));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("Daftar Peliuk Berdasarkan Desa {$desaName}", $datas);
            }
            return $this->success("Daftar Peliuk tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }


    public function createPeliuk($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:peliuk',
                'nama'     => 'required',
                'rt'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $peliuk = new Peliuk();
            $peliuk->id = $request->id; // required
            $peliuk->nama = $request->nama; // required
            $peliuk->rw = $request->rw; // required
            $peliuk->rt = $request->rt; // required



            $create = $peliuk->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Peliuk Berhasil ditambahkan!", $peliuk);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updatePeliuk($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'nama' => 'required',
                'rt' => 'required',

            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }
        try {
            // search
            $peliuk = Peliuk::find($id);
            // check
            if (!$peliuk) {
                return $this->error("Not Found", "Peliuk dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Peliuk::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Peliuk sudah dipakai", 422);
                }

                $peliuk['nama'] = $request->nama;
                $peliuk['rw'] = $request->rw;
                $peliuk['rt'] = $request->rt;

                $update = $peliuk->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Peliuk Berhasil diperharui!", $peliuk);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePeliuk($id)
    {
        try {
            // search
            $peliuk = Peliuk::find($id);
            if (!$peliuk) {
                return $this->error("Not Found", "Peliuk dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $peliuk->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Peliuk dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function importPeliuk($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'file'                  =>  'mimes:csv,txt|
                                            max:5120',
            ],
            [
                'file.mimes'                 => 'Format File tidak didukung!, mohon inputkan File bertipe csv',
                'file.max'                   => 'File terlalu besar, maksimal 5MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Validasi gagal, beberapa field yang anda masukkan tidak sesuai format!", $validator->errors(), 400);
        }

        try {
            if ($request->hasFile('file')) {
                $import = Excel::import(new PeliukImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Peliuk Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Peliuk Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }

    function queryGetModify($datas, $manyResult = false, $paginate = true)
    {
        if ($datas) {
            if ($manyResult) {
                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');
                $modifiedData = array_map(function ($item) {
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
        $item->rt = json_decode($item->rt);
        return $item;
    }
}
