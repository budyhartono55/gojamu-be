<?php

namespace App\Repositories\Ctg_Gallery;

use Illuminate\Http\Request;

interface Ctg_GalleryInterface
{
    // getAll
    public function getCtg_Gallery($request);
    // findOne
    public function findById($id);
    // insertData
    public function createCtg_Gallery($request);
    // update
    public function updateCtg_Gallery($request, $id);
    // delete
    public function deleteCtg_Gallery($id);
}
