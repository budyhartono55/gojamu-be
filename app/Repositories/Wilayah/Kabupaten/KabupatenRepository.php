<?php

namespace App\Repositories\Wilayah\Kabupaten;


use App\Models\Wilayah\Kabupaten;
use App\Repositories\Wilayah\Kabupaten\KabupatenInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\KabupatenImport;
use App\Models\Wilayah\Provinsi;
use Maatwebsite\Excel\Facades\Excel;

class KabupatenRepository implements KabupatenInterface
{
    private $kabupaten;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "kabupaten_";
    use API_response;

    public function __construct(Kabupaten $kabupaten)
    {
        $this->kabupaten = $kabupaten;
    }

    public function getKabupaten($request)
    {

        try {
            $findById = $request->id;
            $findByProvinsi = $request->filled('provinsi') ? $request->provinsi : 52;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? "asc" : "desc";
            $params = $findById . $findByProvinsi . $paginate . $order;

            $provinsi = Provinsi::find($findByProvinsi);
            if (!$provinsi) {
                return $this->success("NOT FOUND", "ID Provinsi ({$findByProvinsi}) Tidak Ditemukan", 404);
            }
            $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;


            $provinsiName = Provinsi::select('nama')->where('id', $findByProvinsi)->first()->nama;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kabupaten Berdasarkan Provinsi {$provinsiName} from (CACHE)", $result);
            }



            $query = Kabupaten::orderBy('id', $order);


            if ($request->filled('provinsi')) {
                $query->where('id', 'LIKE', "{$findByProvinsi}%");
            }



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

                return $this->success("Daftar Kabupaten Berdasarkan Provinsi {$provinsiName}", $result);
            }
            return $this->success("Daftar Kabupaten tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function createKabupaten($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:kabupaten',
                'nama'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $kabupaten = new Kabupaten();
            $kabupaten->id = $request->id; // required
            $kabupaten->nama = $request->nama; // required


            $create = $kabupaten->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Kabupaten Berhasil ditambahkan!", $kabupaten);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateKabupaten($request, $id)
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
            $kabupaten = Kabupaten::find($id);
            // check
            if (!$kabupaten) {
                return $this->error("Not Found", "Kabupaten dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Kabupaten::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Kabupaten sudah dipakai", 422);
                }

                $kabupaten['nama'] = $request->nama;
                $update = $kabupaten->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Kabupaten Berhasil diperharui!", $kabupaten);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteKabupaten($id)
    {
        try {
            // search
            $kabupaten = Kabupaten::find($id);
            if (!$kabupaten) {
                return $this->error("Not Found", "Kabupaten dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $kabupaten->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Kabupaten dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function importKabupaten($request)
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
                $import = Excel::import(new KabupatenImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Kabupaten Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Kabupaten Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }
}
