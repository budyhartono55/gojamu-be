<?php

namespace App\Repositories\Favorite_Media;

use Illuminate\Http\Request;

interface Favorite_MediaInterface
{
    // insertData
    public function toggleFavorite_Media($request);
}