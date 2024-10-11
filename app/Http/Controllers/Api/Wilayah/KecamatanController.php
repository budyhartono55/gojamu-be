<?php

namespace App\Http\Controllers\Api\Wilayah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Wilayah\Kecamatan\KecamatanInterface;

class KecamatanController extends Controller
{
    private $kecamatanRepository;

    public function __construct(KecamatanInterface $kecamatanRepository)
    {
        $this->kecamatanRepository = $kecamatanRepository;
    }


    public function getAll(Request $request)
    {

        return $this->kecamatanRepository->getKecamatan($request);
    }


    public function save(Request $request)
    {
        return $this->kecamatanRepository->createKecamatan($request);
    }

    public function update(Request $request, $id)
    {
        return $this->kecamatanRepository->updateKecamatan($request, $id);
    }

    public function delete($id)
    {
        return $this->kecamatanRepository->deleteKecamatan($id);
    }
}
