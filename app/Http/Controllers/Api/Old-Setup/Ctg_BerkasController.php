<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Berkas\Ctg_BerkasInterface as Ctg_BerkasInterface;


class Ctg_BerkasController extends Controller
{

    private $ctg_berkasRepository;

    public function __construct(Ctg_BerkasInterface $ctg_berkasRepository)
    {
        $this->ctg_berkasRepository = $ctg_berkasRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_berkasRepository->getCtg_Berkas($request);
    }

    //findOne
    public function findById($id)
    {
        return $this->ctg_berkasRepository->findById($id);
    }

    // create
    public function add(Request $request)
    {
        return $this->ctg_berkasRepository->createCtg_Berkas($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_berkasRepository->updateCtg_Berkas($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->ctg_berkasRepository->deleteCtg_Berkas($id);
    }
}
