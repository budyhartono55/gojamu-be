<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Rating\RatingInterface as RatingInterface;

class RatingController extends Controller
{

    private $ratingRepository;

    public function __construct(RatingInterface $ratingRepository)
    {
        $this->ratingRepository = $ratingRepository;
    }

    //M E T H O D E ======================
    // core
    // create
    public function rate(Request $request)
    {
        return $this->ratingRepository->rate($request);
    }
    public function findByIdMedia($id)
    {
        return $this->ratingRepository->getRatingsByMediaId($id);
    }
}