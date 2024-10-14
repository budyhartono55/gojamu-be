<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Liaison\LiaisonInterface;

class LiaisonController extends Controller
{
    private $liaisonRepository;

    public function __construct(LiaisonInterface $liaisonRepository)
    {
        $this->liaisonRepository = $liaisonRepository;
    }

    public function getLiaison(Request $request)
    {
        return $this->liaisonRepository->getLiaison($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->liaisonRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->liaisonRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->liaisonRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->liaisonRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->liaisonRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->liaisonRepository->deletePermanent($id);
    }

    public function restore()
    {
        return $this->liaisonRepository->restore();
    }

    public function restoreById($id)
    {
        return $this->liaisonRepository->restoreById($id);
    }
}
