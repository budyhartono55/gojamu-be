<?php

namespace App\Repositories\HeaderContent;

interface HeaderContentInterface
{
    public function getAll($request);
    public function getById($id);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
}
