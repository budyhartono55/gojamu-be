<?php

namespace App\Repositories\Wilayah\Kabupaten;

interface KabupatenInterface
{
    public function getKabupaten($request);
    public function createKabupaten($request);
    public function updateKabupaten($request, $id);
    public function deleteKabupaten($id);
    public function importKabupaten($request);
}
