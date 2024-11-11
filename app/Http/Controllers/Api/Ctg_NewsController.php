<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Ctg_News\Ctg_NewsInterface as Ctg_NewsInterface;


class Ctg_NewsController extends Controller
{

    private $Ctg_NewsInterface;

    public function __construct(Ctg_NewsInterface $Ctg_NewsInterface)
    {
        $this->Ctg_NewsInterface = $Ctg_NewsInterface;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->Ctg_NewsInterface->getAllCategories($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->Ctg_NewsInterface->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->Ctg_NewsInterface->createCategory($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->Ctg_NewsInterface->updateCategory($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->Ctg_NewsInterface->deleteCategory($id);
    }
}
