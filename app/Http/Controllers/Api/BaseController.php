<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Base\BaseInterface as BaseInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\BaseRequest;
use App\Models\Base;
use App\Repositories\Base\BaseRepository;

class BaseController extends Controller
{

    private $baseRepository;

    public function __construct(BaseInterface $baseRepository)
    {
        $this->baseRepository = $baseRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->baseRepository->getBases($request);
    }

    // create
    public function insert(Request $request)
    {
        return $this->baseRepository->createBase($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->baseRepository->updateBase($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->baseRepository->deleteBase($id);
    }
}
