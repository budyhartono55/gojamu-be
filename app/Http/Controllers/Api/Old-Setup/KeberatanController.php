<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Keberatan\KeberatanInterface;

class KeberatanController extends Controller
{
    private $keberatanRepository;

    public function __construct(KeberatanInterface $keberatanRepository)
    {
        $this->keberatanRepository = $keberatanRepository;
    }

    public function getKeberatan(Request $request)
    {
        return $this->keberatanRepository->getKeberatan($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->keberatanRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->keberatanRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->keberatanRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->keberatanRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->keberatanRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->keberatanRepository->deletePermanent($id);
    }


    // public function getByCategory($id, Request $request)
    // {
    //     return $this->keberatanRepository->getByCategory($id, $request);
    // }

    // public function getAllBy($kondisi, Request $request)
    // {
    //     return $this->keberatanRepository->getAllBy($kondisi, $request);
    // }

    // public function search($keyword, Request $request)
    // {
    //     return $this->keberatanRepository->search($keyword, $request);
    // }

    // public function read($slug)
    // {
    //     return $this->keberatanRepository->read($slug);
    // }

    // public function geLimitKeberatan($limit)
    // {
    //     return $this->keberatanRepository->geLimitKeberatan($limit);
    // }

    // public function mergeKeberatanFromOpd()
    // {
    //     return $this->keberatanRepository->mergeKeberatanFromOpd();
    // }
}
