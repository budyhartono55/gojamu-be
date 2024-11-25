<?php

namespace App\Repositories\Rating;

use Illuminate\Http\Request;

interface RatingInterface
{
    public function rate($request);
    public function getRatingsByMediaId($id);
}