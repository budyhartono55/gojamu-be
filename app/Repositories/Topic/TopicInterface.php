<?php

namespace App\Repositories\Topic;

interface TopicInterface
{
    // getAll
    public function getAll($request);
    // findOne
    // public function findById($id);
    // insertData
    public function create($request);
    // update
    public function update($request, $id);
    // delete
    public function delete($id);
}
