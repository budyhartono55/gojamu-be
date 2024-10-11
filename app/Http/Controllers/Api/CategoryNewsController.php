<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\CategoryNews\CategoryNewsInterface as CategoryNewsInterface;


class CategoryNewsController extends Controller
{

    private $categoryNewsRepository;

    public function __construct(CategoryNewsInterface $categoryNewsRepository)
    {
        $this->categoryNewsRepository = $categoryNewsRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->categoryNewsRepository->getAllCategories($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->categoryNewsRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->categoryNewsRepository->createCategory($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->categoryNewsRepository->updateCategory($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->categoryNewsRepository->deleteCategory($id);
    }
}
