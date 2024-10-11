<?php

namespace App\Repositories\Ctg_Service;

use Illuminate\Http\Request;

interface Ctg_ServiceInterface
{
    // getAll
    public function getCtg_Service($request);
    public function getTotalCategoryServiceData($request);

    // public function getAllCtgService();
    // findOne
    public function findById($id);
    // insertData
    public function createCtg_Service($request);
    // update
    public function updateCtg_Service($request, $id);
    // delete
    public function deleteCtg_Service($id);
}
