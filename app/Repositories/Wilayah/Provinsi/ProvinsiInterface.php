<?php

namespace App\Repositories\Wilayah\Provinsi;

interface ProvinsiInterface
{
    public function getProvinsi($request);
    public function createProvinsi($request);
    public function updateProvinsi($request, $id);
    public function deleteProvinsi($id);
    public function importProvinsi($request);
}
