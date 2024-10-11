<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Dashboard\DashboardInterface;

class DashboardController extends Controller
{

    private $dashboardRepository;

    public function __construct(DashboardInterface $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    //M E T H O D E ======================
    // core
    public function index()
    {
        return $this->dashboardRepository->getAllEachTotalData();
    }
}
