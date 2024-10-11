<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Information\InformationInterface as InformationInterface;


class InformationController extends Controller
{

    private $InformationRepository;

    public function __construct(InformationInterface $InformationRepository)
    {
        $this->InformationRepository = $InformationRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->InformationRepository->getInformations($request);
    }

    // //findByCategoryId
    // public function getByCategorySlug($slug)
    // {
    //     return $this->InformationRepository->getAllInformationByCategorySlug($slug);
    // }

    // // findByKeyword
    // public function getAllByKeyword(Request $keyword)
    // {
    //     return $this->InformationRepository->getAllInformationByKeyword($keyword);
    // }
    // // findByKeywordInCtg
    // public function getAllByKeywordInCtg($slug, Request $keyword)
    // {
    //     return $this->InformationRepository->getAllInformationByKeywordInCtg($slug, $keyword);
    // }

    // //showBySlug
    // public function findBySlug($slug)
    // {
    //     return $this->InformationRepository->showBySlug($slug);
    // }

    // //findOne
    // public function findById($id)
    // {
    //     return $this->InformationRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->InformationRepository->createInformation($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->InformationRepository->updateInformation($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->InformationRepository->deleteInformation($id);
    }
}
