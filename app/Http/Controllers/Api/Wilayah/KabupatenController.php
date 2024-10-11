<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Kabupaten\KabupatenInterface;

class KabupatenController extends Controller
{
    private $kabupatenRepository;

    public function __construct(KabupatenInterface $kabupatenRepository)
    {
        $this->kabupatenRepository = $kabupatenRepository;
    }


    public function getAll(Request $request)
    {

        return $this->kabupatenRepository->getKabupaten($request);
    }


    public function save(Request $request)
    {
        return $this->kabupatenRepository->createKabupaten($request);
    }

    public function update(Request $request, $id)
    {
        return $this->kabupatenRepository->updateKabupaten($request, $id);
    }

    public function delete($id)
    {
        return $this->kabupatenRepository->deleteKabupaten($id);
    }

    public function import(Request $request)
    {
        return $this->kabupatenRepository->importKabupaten($request);
    }
}
