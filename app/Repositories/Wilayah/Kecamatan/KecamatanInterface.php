<?php

namespace App\Repositories\Wilayah\Kecamatan;

interface KecamatanInterface
{
    public function getKecamatan($request);
    public function createKecamatan($request);
    public function updateKecamatan($request, $id);
    public function deleteKecamatan($id);
    public function importKecamatan($request);
}
