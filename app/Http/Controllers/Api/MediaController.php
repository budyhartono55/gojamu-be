<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Media\MediaInterface as MediaInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\MediaRequest;
use App\Models\Media;
use App\Repositories\Media\MediaRepository;

class MediaController extends Controller
{

    private $mediaRepository;

    public function __construct(MediaInterface $mediaRepository)
    {
        $this->mediaRepository = $mediaRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->mediaRepository->getMedias($request);
    }
    public function meta(Request $request)
    {
        return $this->mediaRepository->getMediasOwner($request);
    }

    public function reported()
    {
        return $this->mediaRepository->getAllMediasAttention();
    }
    public function findById($id)
    {
        return $this->mediaRepository->findById($id);
    }
    // create
    public function insert(Request $request)
    {
        return $this->mediaRepository->createMedia($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->mediaRepository->updateMedia($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->mediaRepository->deleteMedia($id);
    }
}
