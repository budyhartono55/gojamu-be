<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Favorite_Media\Favorite_MediaInterface as Favorite_MediaInterface;

class Favorite_MediaController extends Controller
{

    private $favorite_mediaRepository;

    public function __construct(Favorite_MediaInterface $favorite_mediaRepository)
    {
        $this->favorite_mediaRepository = $favorite_mediaRepository;
    }

    //M E T H O D E ======================
    // create
    public function insert(Request $request)
    {
        return $this->favorite_mediaRepository->toggleFavorite_Media($request);
    }
    public function core(Request $request)
    {
        return $this->favorite_mediaRepository->getFavorite($request);
    }
}
