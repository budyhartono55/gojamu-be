<?php

namespace App\Repositories\User;

interface UserInterface
{
    public function getAll($request);
    public function getAllTrash($request);
    public function getById($id);
    public function save($request);
    public function update($request, $id);
    public function deleteSementara($id);
    public function deletePermanent($id);
    public function restore();
    public function restoreById($id);
    public function changePassword($request, $id);
    public function resetPassword($id);
}
