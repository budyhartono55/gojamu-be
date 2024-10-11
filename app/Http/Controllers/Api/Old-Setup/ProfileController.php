<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Profile\ProfileInterface;

class ProfileController extends Controller
{
    private $profileRepository;

    public function __construct(ProfileInterface $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }


    public function getAll(Request $request)
    {

        return $this->profileRepository->getAll($request);
    }

    public function getById($id)
    {

        return $this->profileRepository->getById($id);
    }

    public function save(Request $request)
    {
        return $this->profileRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->profileRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->profileRepository->delete($id);
    }
}
