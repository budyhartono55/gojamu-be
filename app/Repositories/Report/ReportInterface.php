<?php

namespace App\Repositories\Report;

use Illuminate\Http\Request;

interface ReportInterface
{
    // insertData
    // public function getReport($request);
    // public function findById($id);
    public function createReport($request);
    public function deleteReport($id);
}
