<?php

namespace App\Repositories\Pemohon;

use Illuminate\Http\Request;

interface PemohonInterface
{
    // getAll
    public function getPemohon($request);
    // public function getAllPemohons();
    // getAll Pemohon By Category
    // public function getAllPemohonByCtg_Pemohon($id);
    // public function getAllPemohonByCtg_Information($id);
    // getAll Pemohon By Keyword
    // public function findPemohonByCode($codeId);
    // getAll Pemohon By email&nik
    public function findPemohonByEmailAndNik($request);
    // findOne
    // public function findById($id);
    // insertData
    public function createPemohon($request);
    // update
    public function updatePemohon($request, $id);
    // delete
    public function deletePemohon($id);
    // delete
    public function deletePermanent($id);
}
