<?php

namespace App\Repositories\Comment;

use Illuminate\Http\Request;

interface CommentInterface
{
    // getAll
    // public function getComments($request);
    // public function findById($id);
    // insertData
    public function createComment($request);
    // update
    // public function updateComment($request, $id);
    // // delete
    // public function deleteComment($id);
}