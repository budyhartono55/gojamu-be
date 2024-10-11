<?php

namespace App\Repositories\Wilayah\Dusun;


use App\Models\Wilayah\Dusun;
use App\Repositories\Wilayah\Dusun\DusunInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\DusunImport;
use App\Models\Wilayah\Desa;
use Maatwebsite\Excel\Facades\Excel;

class DusunRepository implements DusunInterface
{
    private $dusun;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "dusun_";
    use API_response;

    public function __construct(Dusun $dusun)
    {
        $this->dusun = $dusun;
    }

    public function getDusun($request)
    {

        try {
            $findById = $request->id;
            $findByDesa = $request->desa;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? "asc" : "desc";
            $params = $findById . $findByDesa . $paginate . $order;

            $desa = Desa::find($findByDesa);
            if (!$desa) {
                return $this->success("NOT FOUND", "ID Desa ({$findByDesa}) Tidak Ditemukan", 404);
            }
            $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;


            $desaName = Desa::select('nama')->where('id', $findByDesa)->first()->nama;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Dusun Berdasarkan Desa {$desaName} from (CACHE)", $result);
            }



            $query = Dusun::orderBy('id', $order);


            if ($request->filled('desa')) {
                $query->where('id', 'LIKE', "{$findByDesa}2%");
            }


            if ($request->filled('id')) {
                $query->where('id', $findById);
            }
            if ($paginate == "true") {
                $result = $query->paginate(10);
            } else {
                if ($request->filled('desa')) {
                    $result = $query->get();
                } else {
                    $result = $query->paginate(10);
                }
            }

            // noArray

            if ($result) {
                Redis::set($key, json_encode($result));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("Daftar Dusun Berdasarkan Desa {$desaName}", $result);
            }
            return $this->success("Daftar dusun tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }


    public function createDusun($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:dusun',
                'nama'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $dusun = new Dusun();
            $dusun->id = $request->id; // required
            $dusun->nama = $request->nama; // required


            $create = $dusun->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Dusun Berhasil ditambahkan!", $dusun);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateDusun($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'nama' => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }
        try {
            // search
            $dusun = Dusun::find($id);
            // check
            if (!$dusun) {
                return $this->error("Not Found", "Dusun dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Dusun::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Dusun sudah dipakai", 422);
                }

                $dusun['nama'] = $request->nama;
                $update = $dusun->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Dusun Berhasil diperharui!", $dusun);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteDusun($id)
    {
        try {
            // search
            $dusun = Dusun::find($id);
            if (!$dusun) {
                return $this->error("Not Found", "Dusun dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $dusun->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Dusun dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function importDusun($request)
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
                $import = Excel::import(new DusunImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Dusun Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Dusun Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }
}
