<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Pemohon\PemohonInterface as PemohonInterface;


class PemohonController extends Controller
{

    private $PemohonRepository;

    public function __construct(PemohonInterface $PemohonRepository)
    {
        $this->PemohonRepository = $PemohonRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->PemohonRepository->getPemohon($request);
    }

    //findByCategoryId
    // public function getByCtg_PemohonId($id)
    // {
    //     return $this->PemohonRepository->getAllPemohonByCtg_Pemohon($id);
    // }

    // public function getByCtg_InformationId($id)
    // {
    //     return $this->PemohonRepository->getAllPemohonByCtg_Information($id);
    // }

    // findByKeyword
    // public function findByCode(Request $codeId)
    // {
    //     return $this->PemohonRepository->findPemohonByCode($codeId);
    // }

    public function findByEmailAndNik(Request $request)
    {
        return $this->PemohonRepository->findPemohonByEmailAndNik($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->PemohonRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->PemohonRepository->createPemohon($request);
    }

    // update
    public function edit(Request $request, $id)
    {

        //  return dd($request->all());
        return $this->PemohonRepository->updatePemohon($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->PemohonRepository->deletePemohon($id);
    }
    public function deletePermanent($id)
    {
        return $this->PemohonRepository->deletePermanent($id);
    }
}
