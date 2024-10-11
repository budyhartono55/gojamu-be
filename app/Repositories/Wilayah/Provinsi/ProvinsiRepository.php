<?php

namespace App\Repositories\Wilayah\Provinsi;


use App\Models\Wilayah\Provinsi;
use App\Repositories\Wilayah\Provinsi\ProvinsiInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\ProvinsiImport;
use Maatwebsite\Excel\Facades\Excel;

class ProvinsiRepository implements ProvinsiInterface
{
    private $provinsi;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "provinsi_";
    use API_response;

    public function __construct(Provinsi $provinsi)
    {
        $this->provinsi = $provinsi;
    }

    public function getProvinsi($request)
    {
        $findById = $request->id;
        $paginate = $request->paginate;
        $order = $request->filled('order') ? "asc" : "desc";
        $params = $findById . $paginate . $order;


        $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;



        if (Redis::exists($key)) {
            $result = json_decode(Redis::get($key));
            return $this->success("List Keseluruhan Provinsi from (CACHE)", $result);
        }



        $query = Provinsi::orderBy('id', $order);


        if ($request->filled('id')) {
            $query->where('id', $findById);
        }
        if ($paginate == "true") {
            $result = $query->paginate(10);
        } else {
            $result = $query->get();
        }

        // noArray

        if ($result) {
            Redis::set($key, json_encode($result));
            Redis::expire($key, 60); // Cache for 60 seconds

            return $this->success("Details Provinsi", $result);
        }
        return $this->success("Daftar Provinsi tidak ditemukan", []);
    }

    public function createProvinsi($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:provinsi',
                'nama'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $provinsi = new Provinsi();
            $provinsi->id = $request->id; // required
            $provinsi->nama = $request->nama; // required


            $create = $provinsi->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Provinsi Berhasil ditambahkan!", $provinsi);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateProvinsi($request, $id)
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
            $provinsi = Provinsi::find($id);
            // check
            if (!$provinsi) {
                return $this->error("Not Found", "Provinsi dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Provinsi::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Provinsi sudah dipakai", 422);
                }

                $provinsi['nama'] = $request->nama;
                $update = $provinsi->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Provinsi Berhasil diperharui!", $provinsi);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteProvinsi($id)
    {
        try {
            // search
            $provinsi = Provinsi::find($id);
            if (!$provinsi) {
                return $this->error("Not Found", "Provinsi dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $provinsi->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Provinsi dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function importProvinsi($request)
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
                $import = Excel::import(new ProvinsiImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Provinsi Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Provinsi Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }
}
