<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Achievement\AchievementInterface;

class AchievementController extends Controller
{
    private $achievementRepository;

    public function __construct(AchievementInterface $achievementRepository)
    {
        $this->achievementRepository = $achievementRepository;
    }

    public function getAchievement(Request $request)
    {
        return $this->achievementRepository->getAchievement($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->achievementRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->achievementRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->achievementRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->achievementRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->achievementRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->achievementRepository->deletePermanent($id);
    }

    public function restore()
    {
        return $this->achievementRepository->restore();
    }

    public function restoreById($id)
    {
        return $this->achievementRepository->restoreById($id);
    }


    // public function getByCategory($id, Request $request)
    // {
    //     return $this->achievementRepository->getByCategory($id, $request);
    // }

    // public function getAllBy($kondisi, Request $request)
    // {
    //     return $this->achievementRepository->getAllBy($kondisi, $request);
    // }

    // public function search($keyword, Request $request)
    // {
    //     return $this->achievementRepository->search($keyword, $request);
    // }

    // public function read($slug)
    // {
    //     return $this->achievementRepository->read($slug);
    // }

    // public function geLimitAchievement($limit)
    // {
    //     return $this->achievementRepository->geLimitAchievement($limit);
    // }

    // public function mergeAchievementFromOpd()
    // {
    //     return $this->achievementRepository->mergeAchievementFromOpd();
    // }
}
