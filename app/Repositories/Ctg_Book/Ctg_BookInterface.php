<?php

namespace App\Repositories\Ctg_Book;

interface Ctg_BookInterface
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
