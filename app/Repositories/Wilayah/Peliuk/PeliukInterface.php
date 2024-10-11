<?php

namespace App\Repositories\Wilayah\Peliuk;

interface PeliukInterface
{
    public function getPeliuk($request);
    public function createPeliuk($request);
    public function updatePeliuk($request, $id);
    public function deletePeliuk($id);
    public function importPeliuk($request);
}
