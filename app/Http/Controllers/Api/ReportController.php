<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Report\ReportInterface as ReportInterface;

class ReportController extends Controller
{

    private $reportRepository;

    public function __construct(ReportInterface $reportRepository)
    {
        $this->reportRepository = $reportRepository;
    }

    //M E T H O D E ======================
    public function index(Request $request)
    {
        return $this->reportRepository->getReport($request);
    }
    public function findById($id)
    {
        // return $this->reportRepository->findById($id);
    }
    // create
    public function insert(Request $request)
    {
        return $this->reportRepository->createReport($request);
    }
    public function drop($id)
    {
        return $this->reportRepository->deleteReport($id);
    }
}
