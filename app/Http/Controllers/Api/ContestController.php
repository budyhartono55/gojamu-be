<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Contest\ContestInterface as ContestInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\ContestRequest;
use App\Models\Contest;
use App\Repositories\Contest\ContestRepository;

class ContestController extends Controller
{

    private $baseRepository;

    public function __construct(ContestInterface $baseRepository)
    {
        $this->baseRepository = $baseRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->baseRepository->getContests($request);
    }

    // create
    public function insert(Request $request)
    {
        return $this->baseRepository->createContest($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->baseRepository->updateContest($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->baseRepository->deleteContest($id);
    }
}
