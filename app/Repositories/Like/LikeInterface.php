<?php

namespace App\Repositories\Like;

use Illuminate\Http\Request;

interface LikeInterface
{
    // getAll
    public function getLikes($request);
    // insertData
    public function toggleLike($request);
}
