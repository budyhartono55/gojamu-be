<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\LogActivity\LogActivityInterface;

class LogActivityController extends Controller
{
    private $logActivityRepository;

    public function __construct(LogActivityInterface $logActivityRepository)
    {
        $this->logActivityRepository = $logActivityRepository;
    }


    public function index(Request $request)
    {

        return $this->logActivityRepository->getLogActivity($request);
    }
}
