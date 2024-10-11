<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Berkas_Dinsos\Berkas_DinsosInterface as Berkas_DinsosInterface;


class Berkas_DinsosController extends Controller
{

    private $Berkas_DinsosRepository;

    public function __construct(Berkas_DinsosInterface $Berkas_DinsosRepository)
    {
        $this->Berkas_DinsosRepository = $Berkas_DinsosRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->Berkas_DinsosRepository->getBerkas_Dinsos($request);
    }

    // create
    public function add(Request $request)
    {
        return $this->Berkas_DinsosRepository->createBerkas_Dinsos($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->Berkas_DinsosRepository->updateBerkas_Dinsos($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->Berkas_DinsosRepository->deleteBerkas_Dinsos($id);
    }

    public function deletePermanent($id)
    {
        return $this->Berkas_DinsosRepository->deletePermanent($id);
    }
}
