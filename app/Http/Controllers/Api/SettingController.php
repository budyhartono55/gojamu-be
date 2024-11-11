<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Setting\SettingInterface;


use Illuminate\Http\Request;

class SettingController extends Controller
{
    private $settingRepository;

    public function __construct(SettingInterface $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }


    public function getAll(Request $request)
    {

        return $this->settingRepository->getAll($request);
    }

    // public function getById($id)
    // {

    //     return $this->settingRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->settingRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->settingRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->settingRepository->delete($id);
    }
}
