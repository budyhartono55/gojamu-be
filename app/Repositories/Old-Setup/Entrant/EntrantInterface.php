<?php

namespace App\Repositories\Entrant;

use App\Http\Requests\EntrantRequest;

use Illuminate\Http\Request;

interface EntrantInterface
{
    // getAll
    public function getEntrants($request);
    // insertData
    public function createEntrant($request);
    // update
    public function updateEntrant($request, $id);
    // delete
    public function deleteEntrant($id);
}
