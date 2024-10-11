<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Gallery\Ctg_GalleryInterface as Ctg_GalleryInterface;


class Ctg_GalleryController extends Controller
{

    private $ctg_galleryRepository;

    public function __construct(Ctg_GalleryInterface $ctg_galleryRepository)
    {
        $this->ctg_galleryRepository = $ctg_galleryRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->ctg_galleryRepository->getCtg_Gallery($request);
    }

    //findOne
    public function findById($id)
    {
        return $this->ctg_galleryRepository->findById($id);
    }

    // create
    public function add(Request $request)
    {
        return $this->ctg_galleryRepository->createCtg_Gallery($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->ctg_galleryRepository->updateCtg_Gallery($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->ctg_galleryRepository->deleteCtg_Gallery($id);
    }
}
