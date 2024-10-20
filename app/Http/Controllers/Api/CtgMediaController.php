<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\CtgMedia\CtgMediaInterface as CtgMediaInterface;


class CtgMediaController extends Controller
{

    private $ctg_mediaRepository;

    public function __construct(CtgMediaInterface $ctg_mediaRepository)
    {
        $this->ctg_mediaRepository = $ctg_mediaRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_mediaRepository->getCtgMedia($request);
    }
    //findOne
    public function findById($id)
    {
        return $this->ctg_mediaRepository->findById($id);
    }

    // create
    public function insert(Request $request)
    {
        return $this->ctg_mediaRepository->createCtgMedia($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_mediaRepository->updateCtgMedia($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->ctg_mediaRepository->deleteCtgMedia($id);
    }
}
