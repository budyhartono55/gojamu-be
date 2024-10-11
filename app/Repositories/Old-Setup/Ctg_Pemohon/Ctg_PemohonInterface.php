<?php

namespace App\Repositories\Ctg_Pemohon;

use Illuminate\Http\Request;

interface Ctg_PemohonInterface
{
    // getAll
    public function getCtg_Pemohon($request);
    // public function getAllCtg_Pemohon();
    // findOne
    public function findById($id);
    // insertData
    public function createCtg_Pemohon($request);
    // update
    public function updateCtg_Pemohon($request, $id);
    // delete
    public function deleteCtg_Pemohon($id);
}
