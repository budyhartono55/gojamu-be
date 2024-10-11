<?php

namespace App\Repositories\Wilayah\Dusun;

interface DusunInterface
{
    public function getDusun($request);
    public function createDusun($request);
    public function updateDusun($request, $id);
    public function deleteDusun($id);
    public function importDusun($request);
}
