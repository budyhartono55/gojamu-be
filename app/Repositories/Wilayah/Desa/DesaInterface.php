<?php

namespace App\Repositories\Wilayah\Desa;

interface DesaInterface
{
    public function getDesa($request);
    public function createDesa($request);
    public function updateDesa($request, $id);
    public function deleteDesa($id);
    public function importDesa($request);
}
