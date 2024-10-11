<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Desa\DesaInterface;

class DesaController extends Controller
{
    private $desaRepository;

    public function __construct(DesaInterface $desaRepository)
    {
        $this->desaRepository = $desaRepository;
    }


    public function getAll(Request $request)
    {

        return $this->desaRepository->getDesa($request);
    }


    public function save(Request $request)
    {
        return $this->desaRepository->createDesa($request);
    }

    public function update(Request $request, $id)
    {
        return $this->desaRepository->updateDesa($request, $id);
    }

    public function delete($id)
    {
        return $this->desaRepository->deleteDesa($id);
    }

    public function import(Request $request)
    {
        return $this->desaRepository->importDesa($request);
    }
}
