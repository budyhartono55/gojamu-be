<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Service\ServiceInterface as ServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\ServiceRequest;
use App\Models\Service;
use App\Repositories\Service\ServiceRepository;

class ServiceController extends Controller
{

    private $serviceRepository;

    public function __construct(ServiceInterface $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    //M E T H O D E ======================
    // core
    public function index()
    {
        return $this->serviceRepository->getAllServices();
    }

    //findOne
    public function findById($id)
    {
        return $this->serviceRepository->findById($id);
    }
    //byKeyword
    public function getAllByKeyword(Request $keyword)
    {
        return $this->serviceRepository->getAllServiceByKeyword($keyword);
    }

    // create
    public function add(Request $request)
    {
        return $this->serviceRepository->createService($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->serviceRepository->updateService($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->serviceRepository->deleteService($id);
    }
}
