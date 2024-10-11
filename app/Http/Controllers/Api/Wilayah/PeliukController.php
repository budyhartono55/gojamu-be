<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Peliuk\PeliukInterface;

class PeliukController extends Controller
{
    private $peliukRepository;

    public function __construct(PeliukInterface $peliukRepository)
    {
        $this->peliukRepository = $peliukRepository;
    }


    public function getAll(Request $request)
    {

        return $this->peliukRepository->getPeliuk($request);
    }


    public function save(Request $request)
    {
        return $this->peliukRepository->createPeliuk($request);
    }

    public function update(Request $request, $id)
    {
        return $this->peliukRepository->updatePeliuk($request, $id);
    }

    public function delete($id)
    {
        return $this->peliukRepository->deletePeliuk($id);
    }

    public function import(Request $request)
    {
        return $this->peliukRepository->importPeliuk($request);
    }
}
