<?php

namespace App\Repositories\Like;

use Illuminate\Http\Request;

interface LikeInterface
{
    // insertData
    public function toggleLike($request);
}
