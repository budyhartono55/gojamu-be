<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Entrant\EntrantInterface as EntrantInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\EntrantRequest;
use App\Models\Entrant;
use App\Repositories\Entrant\EntrantRepository;

class EntrantController extends Controller
{

    private $entrantRepository;

    public function __construct(EntrantInterface $entrantRepository)
    {
        $this->entrantRepository = $entrantRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->entrantRepository->getEntrants($request);
    }

    // create
    public function insert(Request $request)
    {
        return $this->entrantRepository->createEntrant($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->entrantRepository->updateEntrant($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->entrantRepository->deleteEntrant($id);
    }
}
