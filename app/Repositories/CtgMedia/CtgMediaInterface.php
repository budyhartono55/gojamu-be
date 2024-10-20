<?php

namespace App\Repositories\CtgMedia;

use Illuminate\Http\Request;

interface CtgMediaInterface
{
    // getAll
    public function getCtgMedia($request);
    // public function getAllCtMedia();
    // findOne
    public function findById($id);
    // insertData
    public function createCtgMedia($request);
    // update
    public function updateCtgMedia($request, $id);
    // delete
    public function deleteCtgMedia($id);
}
