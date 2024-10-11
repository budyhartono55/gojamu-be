<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Provinsi\ProvinsiInterface;

class ProvinsiController extends Controller
{
    private $provinsiRepository;

    public function __construct(ProvinsiInterface $provinsiRepository)
    {
        $this->provinsiRepository = $provinsiRepository;
    }


    public function getAll(Request $request)
    {

        return $this->provinsiRepository->getProvinsi($request);
    }


    public function save(Request $request)
    {
        return $this->provinsiRepository->createProvinsi($request);
    }

    public function update(Request $request, $id)
    {
        return $this->provinsiRepository->updateProvinsi($request, $id);
    }

    public function delete($id)
    {
        return $this->provinsiRepository->deleteProvinsi($id);
    }

    public function import(Request $request)
    {
        return $this->provinsiRepository->importProvinsi($request);
    }
}
