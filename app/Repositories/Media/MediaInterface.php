<?php

namespace App\Repositories\Service;

use App\Http\Requests\ServiceRequest;

use Illuminate\Http\Request;

interface ServiceInterface
{
    // getAll
    public function getServices($request);
    public function findById($id);
    // insertData
    public function createService($request);
    // update
    public function updateService($request, $id);
    // delete
    public function deleteService($id);
}
