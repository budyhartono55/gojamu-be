<?php

namespace App\Repositories\Information;

use Illuminate\Http\Request;

interface InformationInterface
{
    //main
    public function getInformations($request);
    // getAll
    // public function getAllInformations($request);
    // // getAll Information By Category
    // public function getAllInformationByCategorySlug($slug);
    // // getAll Information By Keyword
    // public function getAllInformationByKeyword($keyword);
    // // getAll Information By Keyword in category
    // public function getAllInformationByKeywordInCtg($slug, $keyword);
    // //readBySlug 
    // public function showBySlug($slug);
    // // findOne
    // public function findById($id);
    // insertData
    public function createInformation($request);
    // update
    public function updateInformation($request, $id);
    // delete
    public function deleteInformation($id);
}