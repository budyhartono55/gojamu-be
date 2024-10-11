<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Service\Ctg_ServiceInterface as Ctg_ServiceInterface;


class Ctg_ServiceController extends Controller
{

    private $ctg_serviceRepository;

    public function __construct(Ctg_ServiceInterface $ctg_serviceRepository)
    {
        $this->ctg_serviceRepository = $ctg_serviceRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_serviceRepository->getCtg_Service($request);
    }
    public function dashboard(Request $request)
    {
        return $this->ctg_serviceRepository->getTotalCategoryServiceData($request);
    }
    //findOne
    public function findById($id)
    {
        return $this->ctg_serviceRepository->findById($id);
    }

    // create
    public function insert(Request $request)
    {
        return $this->ctg_serviceRepository->createCtg_Service($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_serviceRepository->updateCtg_Service($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->ctg_serviceRepository->deleteCtg_Service($id);
    }
}
