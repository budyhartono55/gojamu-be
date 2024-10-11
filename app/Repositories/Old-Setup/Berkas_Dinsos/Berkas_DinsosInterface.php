<?php

namespace App\Repositories\Berkas_Dinsos;

interface Berkas_DinsosInterface
{
    //main
    public function getBerkas_Dinsos($request);

    public function createBerkas_Dinsos($request);
    // update
    public function updateBerkas_Dinsos($request, $id);
    // delete
    public function deleteBerkas_Dinsos($id);
    public function deletePermanent($id);
}
