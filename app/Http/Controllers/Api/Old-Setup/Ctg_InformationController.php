<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Information\Ctg_InformationInterface as Ctg_InformationInterface;


class Ctg_InformationController extends Controller
{

    private $ctg_informationRepository;

    public function __construct(Ctg_InformationInterface $ctg_informationRepository)
    {
        $this->ctg_informationRepository = $ctg_informationRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_informationRepository->getCtg_Information($request);
    }

    //findOne
    public function findById($id)
    {
        return $this->ctg_informationRepository->findById($id);
    }

    // create
    public function add(Request $request)
    {
        return $this->ctg_informationRepository->createCtg_Information($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_informationRepository->updateCtg_Information($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->ctg_informationRepository->deleteCtg_Information($id);
    }
}
