<?php

namespace App\Repositories\Profile;

interface ProfileInterface
{
    public function getAll($request);
    public function getById($id);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
}
