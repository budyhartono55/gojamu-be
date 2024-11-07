<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_News\CtgNewsInterface as Ctg_NewsInterface;


class CategoryNewsController extends Controller
{

    private $Ctg_NewsRepository;

    public function __construct(Ctg_NewsInterface $Ctg_NewsRepository)
    {
        $this->Ctg_NewsRepository = $Ctg_NewsRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->Ctg_NewsRepository->getAllCategories($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->Ctg_NewsRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->Ctg_NewsRepository->createCategory($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->Ctg_NewsRepository->updateCategory($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->Ctg_NewsRepository->deleteCategory($id);
    }
}
