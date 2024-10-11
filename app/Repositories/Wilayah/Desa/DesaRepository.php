<?php

namespace App\Repositories\Wilayah\Desa;


use App\Models\Wilayah\Desa;
use App\Repositories\Wilayah\Desa\DesaInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\DesaImport;
use App\Models\Wilayah\Kecamatan;
use Maatwebsite\Excel\Facades\Excel;

class DesaRepository implements DesaInterface
{
    private $desa;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "desa_";
    use API_response;

    public function __construct(Desa $desa)
    {
        $this->desa = $desa;
    }

    public function getDesa($request)
    {

        try {
            $findById = $request->id;
            $findByKecamatan = $request->kecamatan;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? "asc" : "desc";
            $params = $findById . $findByKecamatan . $paginate . $order;
            $kecamatan = Kecamatan::find($findByKecamatan);
            if (!$kecamatan) {
                return $this->success("NOT FOUND", "ID Kecamatan ({$findByKecamatan}) Tidak Ditemukan", 404);
            }

            $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;


            $kecamatanName = Kecamatan::select('nama')->where('id', $findByKecamatan)->first()->nama;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Desa Berdasarkan Kecamatan {$kecamatanName} from (CACHE)", $result);
            }



            $query = Desa::orderBy('id', $order);


            if ($request->filled('kecamatan')) {
                $query->where('id', 'LIKE', "{$findByKecamatan}%");
            }


            if ($request->filled('id')) {
                $query->where('id', $findById);
            }
            if ($paginate == "true") {
                $result = $query->paginate(10);
            } else {
                if ($request->filled('kecamatan')) {
                    $result = $query->get();
                } else {
                    $result = $query->paginate(10);
                }
            }

            // noArray

            if ($result) {
                Redis::set($key, json_encode($result));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("Daftar Desa Berdasarkan Kecamatan {$kecamatanName}", $result);
            }
            return $this->success("Daftar desa tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }


    public function createDesa($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:desa',
                'nama'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $desa = new Desa();
            $desa->id = $request->id; // required
            $desa->nama = $request->nama; // required


            $create = $desa->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Desa Berhasil ditambahkan!", $desa);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateDesa($request, $id)
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
            $desa = Desa::find($id);
            // check
            if (!$desa) {
                return $this->error("Not Found", "Desa dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Desa::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Desa sudah dipakai", 422);
                }

                $desa['nama'] = $request->nama;
                $update = $desa->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Desa Berhasil diperharui!", $desa);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteDesa($id)
    {
        try {
            // search
            $desa = Desa::find($id);
            if (!$desa) {
                return $this->error("Not Found", "Desa dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $desa->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Desa dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function importDesa($request)
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
                $import = Excel::import(new DesaImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Desa Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Desa Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }
}
