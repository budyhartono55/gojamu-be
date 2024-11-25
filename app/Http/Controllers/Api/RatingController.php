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
    public function index(Request $request)
    {
        return $this->ratingRepository->getRatings($request);
    }

    //findOne
    public function findById($id)
    {
        return $this->ratingRepository->findById($id);
    }

    // create
    public function rate(Request $request)
    {
        return $this->ratingRepository->rate($request);
    }

    // update
    // public function edit(Request $request, $id)
    // {

    //     //  return dd($request->all());
    //     return $this->ratingRepository->updateRating($request, $id);
    // }

    // // delete
    // public function delete($id)
    // {
    //     return $this->ratingRepository->deleteRating($id);
    // }
}