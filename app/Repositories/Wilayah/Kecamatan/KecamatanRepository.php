<?php

namespace App\Repositories\Wilayah\Kecamatan;


use App\Models\Wilayah\Kecamatan;
use App\Repositories\Wilayah\Kecamatan\KecamatanInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Imports\KecamatanImport;
use App\Models\Wilayah\Kabupaten;
use Maatwebsite\Excel\Facades\Excel;

class KecamatanRepository implements KecamatanInterface
{
    private $kecamatan;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "kecamatan_";
    use API_response;

    public function __construct(Kecamatan $kecamatan)
    {
        $this->kecamatan = $kecamatan;
    }

    public function getKecamatan($request)
    {

        try {
            $findById = $request->id;
            $findByKabupaten = $request->kabupaten;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? "asc" : "desc";
            $params = $findById . $findByKabupaten . $paginate . $order;

            $kabupaten = Kabupaten::find($findByKabupaten);
            if (!$kabupaten) {
                return $this->success("NOT FOUND", "ID Kabupaten ({$findByKabupaten}) Tidak Ditemukan", 404);
            }
            $key = $this->keyRedis . "All" . request()->get('page', 1) . "#params" . $params;


            $kabupatenName = Kabupaten::select('nama')->where('id', $findByKabupaten)->first()->nama;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Kecamatan Berdasarkan Kabupaten {$kabupatenName} from (CACHE)", $result);
            }



            $query = Kecamatan::orderBy('id', $order);


            if ($request->filled('kabupaten')) {
                $query->where('id', 'LIKE', "{$findByKabupaten}%");
            }


            if ($request->filled('id')) {
                $query->where('id', $findById);
            }
            if ($paginate == "true") {
                $result = $query->paginate(10);
            } else {
                if ($request->filled('kabupaten')) {
                    $result = $query->get();
                } else {
                    $result = $query->paginate(10);
                }
            }

            // noArray

            if ($result) {
                Redis::set($key, json_encode($result));
                Redis::expire($key, 60); // Cache for 60 seconds

                return $this->success("Daftar Kecamatan Berdasarkan Kabupaten {$kabupatenName}", $result);
            }
            return $this->success("Daftar Kecamatan tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function createKecamatan($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'id'     => 'required|unique:kecamatan',
                'nama'     => 'required',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }

        try {
            $kecamatan = new Kecamatan();
            $kecamatan->id = $request->id; // required
            $kecamatan->nama = $request->nama; // required


            $create = $kecamatan->save();

            if ($create) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("Kecamatan Berhasil ditambahkan!", $kecamatan);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function updateKecamatan($request, $id)
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
            $kecamatan = Kecamatan::find($id);
            // check
            if (!$kecamatan) {
                return $this->error("Not Found", "Kecamatan dengan ID = ($id) tidak ditemukan!", 404);
            } else {

                $cekNama = Kecamatan::where('nama', $request->nama)->exists();
                if ($cekNama and $request->nama !== "") {
                    return $this->error("Upps, Validation Failed!", "Nama Kecamatan sudah dipakai", 422);
                }

                $kecamatan['nama'] = $request->nama;
                $update = $kecamatan->save();
                if ($update) {
                    Helper::deleteRedis($this->keyRedis . "*");
                    return $this->success("Kecamatan Berhasil diperharui!", $kecamatan);
                }
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deleteKecamatan($id)
    {
        try {
            // search
            $kecamatan = Kecamatan::find($id);
            if (!$kecamatan) {
                return $this->error("Not Found", "Kecamatan dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $kecamatan->delete();
            if ($del) {
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "Kecamatan dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    public function importKecamatan($request)
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
                $import = Excel::import(new KecamatanImport, request()->file('file'));
                if ($import) {
                    return $this->success("Berhasil!", "Import data Kecamatan Berhasil");
                } else {
                    return $this->error("Gagal!", "Import data Kecamatan Gagal!", 400);
                }
            } else {
                return $this->error("Terjadi Kesalahan!", "Mohon Masukkan file Anda!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), 499);
        }
    }
}
