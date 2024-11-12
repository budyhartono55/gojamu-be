<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Like\LikeInterface as LikeInterface;

class LikeController extends Controller
{

    private $likeRepository;

    public function __construct(LikeInterface $likeRepository)
    {
        $this->likeRepository = $likeRepository;
    }

    //M E T H O D E ======================
    // create
    public function insert(Request $request)
    {
        return $this->likeRepository->toggleLike($request);
    }
}
