<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Sponsor\SponsorInterface;

class SponsorController extends Controller
{
    private $sponsorRepository;

    public function __construct(SponsorInterface $sponsorRepository)
    {
        $this->sponsorRepository = $sponsorRepository;
    }

    public function getSponsor(Request $request)
    {
        return $this->sponsorRepository->getSponsor($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->sponsorRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->sponsorRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->sponsorRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->sponsorRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->sponsorRepository->delete($id);
    }
}
