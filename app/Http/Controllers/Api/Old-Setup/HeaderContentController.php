<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\HeaderContent\HeaderContentInterface;


use Illuminate\Http\Request;

class HeaderContentController extends Controller
{
    private $headerContentRepository;

    public function __construct(HeaderContentInterface $headerContentRepository)
    {
        $this->headerContentRepository = $headerContentRepository;
    }


    public function getAll(Request $request)
    {

        return $this->headerContentRepository->getAll($request);
    }

    public function getById($id)
    {

        return $this->headerContentRepository->getById($id);
    }

    public function save(Request $request)
    {
        return $this->headerContentRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->headerContentRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->headerContentRepository->delete($id);
    }
}
