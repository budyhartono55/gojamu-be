<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Gallery\GalleryInterface as GalleryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\GalleryRequest;
use App\Models\Gallery;
use App\Repositories\Gallery\GalleryRepository;

class GalleryController extends Controller
{

    private $galleryRepository;

    public function __construct(GalleryInterface $galleryRepository)
    {
        $this->galleryRepository = $galleryRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->galleryRepository->getGalleries($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->galleryRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->galleryRepository->createGallery($request);
    }

    // update
    public function edit(Request $request, $id)
    {

        //  return dd($request->all());
        return $this->galleryRepository->updateGallery($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->galleryRepository->deleteGallery($id);
    }
}
