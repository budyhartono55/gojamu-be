<?php

namespace App\Repositories\Rating;

use Illuminate\Http\Request;

interface RatingInterface
{
    // getAll
    public function getRatings($request);
    // findOne
    public function findById($id);
    // insertData
    // public function createRating($request);
    public function rate($request);
    // update
    // public function updateRating($request, $id);
    // // delete
    // public function deleteRating($id);
}