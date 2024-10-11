<?php

namespace App\Repositories\Achievement;

interface AchievementInterface
{
    public function getAchievement($request);

    // public function getAll($request);
    // public function getById($id);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
    public function deletePermanent($id);
    public function restore();
    public function restoreById($id);
    // public function getByCategory($id, $request);    
    // public function getAllBy($kondisi, $request);
    // public function search($keyword, $request);
    // public function read($slug);
    // public function geLimitAchievement($limit);
    // public function mergeAchievementFromOpd();
}
