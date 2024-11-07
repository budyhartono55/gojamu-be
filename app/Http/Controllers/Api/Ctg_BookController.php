<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Book\CtgBookInterface as Ctg_BookInterface;


class Ctg_BookController extends Controller
{

    private $Ctg_BookRepository;

    public function __construct(Ctg_BookInterface $Ctg_BookRepository)
    {
        $this->Ctg_BookRepository = $Ctg_BookRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->Ctg_BookRepository->getAllCategories($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->Ctg_BookRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->Ctg_BookRepository->createCategory($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->Ctg_BookRepository->updateCategory($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->Ctg_BookRepository->deleteCategory($id);
    }
}
