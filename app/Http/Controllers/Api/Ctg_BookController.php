<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_Book\Ctg_BookInterface as Ctg_BookInterface;


class Ctg_BookController extends Controller
{

    private $Ctg_BookInterface;

    public function __construct(Ctg_BookInterface $Ctg_BookInterface)
    {
        $this->Ctg_BookInterface = $Ctg_BookInterface;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->Ctg_BookInterface->getAllCategories($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->Ctg_BookInterface->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->Ctg_BookInterface->createCategory($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->Ctg_BookInterface->updateCategory($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->Ctg_BookInterface->deleteCategory($id);
    }
}
