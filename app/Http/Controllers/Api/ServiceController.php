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
    public function index(Request $request)
    {
        return $this->serviceRepository->getServices($request);
    }
    // public function tourism(Request $request)
    // {
    //     return $this->serviceRepository->getGmapsServices($request);
    // }
    // public function tourismImage($photo_reference)
    // {
    //     return $this->serviceRepository->getImageGmapsServices($photo_reference);
    // }
    // create
    public function insert(Request $request)
    {
        return $this->serviceRepository->createService($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->serviceRepository->updateService($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->serviceRepository->deleteService($id);
    }
}
