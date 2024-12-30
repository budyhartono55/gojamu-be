<?php

namespace App\Repositories\Media;

use Illuminate\Http\Request;

interface MediaInterface
{
    // getAll
    public function getMedias($request);
    public function getMediasOwner($request);
    public function findById($id);
    // insertData
    public function createMedia($request);
    // update
    public function updateMedia($request, $id);
    // delete
    public function deleteMedia($id);

    public function getAllMediasAttention();
}
