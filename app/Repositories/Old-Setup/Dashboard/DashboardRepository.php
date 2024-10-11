<?php

namespace App\Repositories\Dashboard;

use App\Models\Gallery;
use App\Models\Information;
use App\Repositories\Dashboard\DashboardInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\News;
use App\Models\Pemohon;
use App\Models\Service;

class DashboardRepository implements DashboardInterface
{

    // Response API HANDLER
    use API_response;
    // getAll
    public function getAllEachTotalData()
    {
        try {
            $key = "Dashboard-countData";
            function countData()
            {
                // $currentYear = date('Y');
                // $countBerita = News::whereYear('created_at', $currentYear)
                //     ->get();
                // $countLayanan = Service::whereYear('created_at', $currentYear)
                //     ->get();
                // $countPemohon = Service::whereYear('created_at', $currentYear)
                //     ->get();
                // $countInformasi = Information::whereYear('created_at', $currentYear)
                //     ->get();
                // $countGaleri = Gallery::whereYear('created_at', $currentYear)
                //     ->get();
                $list = [
                    'jumlah_berita' => News::count(),
                    'jumlah_layanan' => Service::count(),
                    'jumlah_pemohon' => Pemohon::count(),
                    'jumlah_informasi' => Information::count(),
                    'jumlah_galeri' => Gallery::count(),
                ];
                return $list;
            }


            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Keseluruhan Jumlah Data from (CACHE)", $result);
            };
            $res = [
                'data' => countData()
            ];

            if ($res) {
                Redis::set($key, json_encode($res));
                Redis::expire($key, 86400);
                return $this->success("List kesuluruhan Jumlah Data", $res);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage(), $e->getCode());
        }
    }
}
