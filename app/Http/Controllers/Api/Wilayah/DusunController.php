<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Dusun\DusunInterface;

class DusunController extends Controller
{
    private $dusunRepository;

    public function __construct(DusunInterface $dusunRepository)
    {
        $this->dusunRepository = $dusunRepository;
    }


    public function getAll(Request $request)
    {

        return $this->dusunRepository->getDusun($request);
    }


    public function save(Request $request)
    {
        return $this->dusunRepository->createDusun($request);
    }

    public function update(Request $request, $id)
    {
        return $this->dusunRepository->updateDusun($request, $id);
    }

    public function delete($id)
    {
        return $this->dusunRepository->deleteDusun($id);
    }

    public function import(Request $request)
    {
        return $this->dusunRepository->importDusun($request);
    }
}
