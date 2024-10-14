<?php

namespace App\Repositories\Base;

use App\Http\Requests\BaseRequest;

use Illuminate\Http\Request;

interface BaseInterface
{
    // getAll
    public function getBases($request);
    // insertData
    public function createBase($request);
    // update
    public function updateBase($request, $id);
    // delete
    public function deleteBase($id);
}
