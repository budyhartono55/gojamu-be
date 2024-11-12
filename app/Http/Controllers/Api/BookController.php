<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Book\BookInterface;

class BookController extends Controller
{
    private $bookRepository;

    public function __construct(BookInterface $bookRepository)
    {
        $this->bookRepository = $bookRepository;
    }

    public function getBook(Request $request)
    {
        return $this->bookRepository->getBook($request);
    }
    // public function getAll(Request $request)
    // {
    //     return $this->bookRepository->getAll($request);
    // }

    // public function getById($id)
    // {

    //     return $this->bookRepository->getById($id);
    // }

    public function save(Request $request)
    {
        return $this->bookRepository->save($request);
    }

    public function update(Request $request, $id)
    {
        return $this->bookRepository->update($request, $id);
    }

    public function delete($id)
    {
        return $this->bookRepository->delete($id);
    }

    public function deletePermanent($id)
    {
        return $this->bookRepository->deletePermanent($id);
    }

    public function restore()
    {
        return $this->bookRepository->restore();
    }

    public function restoreById($id)
    {
        return $this->bookRepository->restoreById($id);
    }

    public function markAsFavorite($id)
    {
        return $this->bookRepository->markAsFavorite($id);
    }
    public function removeFavorite($id)
    {
        return $this->bookRepository->removeFavorite($id);
    }
    public function getFavoriteBooks(Request $request)
    {
        return $this->bookRepository->getFavoriteBooks($request);
    }
    public function getUsersWhoFavoritedBook($id)
    {
        return $this->bookRepository->getUsersWhoFavoritedBook($id);
    }


    // public function getByCategory($id, Request $request)
    // {
    //     return $this->bookRepository->getByCategory($id, $request);
    // }

    // public function getAllBy($kondisi, Request $request)
    // {
    //     return $this->bookRepository->getAllBy($kondisi, $request);
    // }

    // public function search($keyword, Request $request)
    // {
    //     return $this->bookRepository->search($keyword, $request);
    // }

    // public function read($slug)
    // {
    //     return $this->bookRepository->read($slug);
    // }

    // public function geLimitBook($limit)
    // {
    //     return $this->bookRepository->geLimitBook($limit);
    // }

    // public function mergeBookFromOpd()
    // {
    //     return $this->bookRepository->mergeBookFromOpd();
    // }
}
