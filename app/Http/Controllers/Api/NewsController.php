<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\News\NewsInterface;

class NewsController extends Controller
{
    private $newsRepository;

    public function __construct(NewsInterface $newsRepository)
    {
        $this->newsRepository = $newsRepository;
    }

    public function getNews(Request $request)
    {
        return $this->newsRepository->getNews($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->newsRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->newsRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->newsRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->newsRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->newsRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->newsRepository->deletePermanent($id);
    }

    public function restore()
    {
        return $this->newsRepository->restore();
    }

    public function restoreById($id)
    {
        return $this->newsRepository->restoreById($id);
    }


    // public function getByCategory($id, Request $request)
    // {
    //     return $this->newsRepository->getByCategory($id, $request);
    // }

    // public function getAllBy($kondisi, Request $request)
    // {
    //     return $this->newsRepository->getAllBy($kondisi, $request);
    // }

    // public function search($keyword, Request $request)
    // {
    //     return $this->newsRepository->search($keyword, $request);
    // }

    // public function read($slug)
    // {
    //     return $this->newsRepository->read($slug);
    // }

    // public function geLimitNews($limit)
    // {
    //     return $this->newsRepository->geLimitNews($limit);
    // }

    // public function mergeNewsFromOpd()
    // {
    //     return $this->newsRepository->mergeNewsFromOpd();
    // }
}
