<?php

namespace App\Repositories\Contest;

use App\Http\Requests\ContestRequest;

use Illuminate\Http\Request;

interface ContestInterface
{
    // getAll
    public function getContests($request);
    // insertData
    public function createContest($request);
    // update
    public function updateContest($request, $id);
    // delete
    public function deleteContest($id);
}
