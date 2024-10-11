<?php

namespace App\Repositories\Ctg_Berkas;

use Illuminate\Http\Request;

interface Ctg_BerkasInterface
{
    // getAll
    public function getCtg_Berkas($request);
    // public function getAllCtg_Berkas();
    // findOne
    public function findById($id);
    // insertData
    public function createCtg_Berkas($request);
    // update
    public function updateCtg_Berkas($request, $id);
    // delete
    public function deleteCtg_Berkas($id);
}
