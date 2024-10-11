<?php

namespace App\Repositories\Ctg_Information;

use Illuminate\Http\Request;

interface Ctg_InformationInterface
{
    // getAll
    public function getCtg_Information($request);
    // public function getAllCtg_Information();
    // findOne
    public function findById($id);
    // insertData
    public function createCtg_Information($request);
    // update
    public function updateCtg_Information($request, $id);
    // delete
    public function deleteCtg_Information($id);
}
