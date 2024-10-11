<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Pemohon\Ctg_PemohonInterface as Ctg_PemohonInterface;


class Ctg_PemohonController extends Controller
{

    private $ctg_pemohonRepository;

    public function __construct(Ctg_PemohonInterface $ctg_pemohonRepository)
    {
        $this->ctg_pemohonRepository = $ctg_pemohonRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_pemohonRepository->getCtg_Pemohon($request);
    }

    //findOne
    public function findById($id)
    {
        return $this->ctg_pemohonRepository->findById($id);
    }

    // create
    public function add(Request $request)
    {
        return $this->ctg_pemohonRepository->createCtg_Pemohon($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_pemohonRepository->updateCtg_Pemohon($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->ctg_pemohonRepository->deleteCtg_Pemohon($id);
    }
}
