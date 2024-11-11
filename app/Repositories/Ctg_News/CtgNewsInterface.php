<?php

namespace App\Repositories\Ctg_News;

interface CtgNewsInterface
{
    // getAll
    public function getAllCategories($request);
    // findOne
    // public function findById($id);
    // insertData
    public function createCategory($request);
    // update
    public function updateCategory($request, $id);
    // delete
    public function deleteCategory($id);
}
