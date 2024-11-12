<?php

namespace App\Repositories\Book;

interface BookInterface
{
    public function getBook($request);

    // public function getAll($request);
    // public function getById($id);
    public function save($request);
    public function update($request, $id);
    public function delete($id);
    public function deletePermanent($id);
    public function restore();
    public function restoreById($id);
    public function getFavoriteBooks($request);
    public function removeFavorite($id);
    public function markAsFavorite($id);
    public function getUsersWhoFavoritedBook($id);
    // public function search($keyword, $request);
    // public function read($slug);
    // public function geLimitBook($limit);
    // public function mergeBookFromOpd();
}
