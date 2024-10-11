<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\GetFile\GetFileInterface;


class GetFileController extends Controller
{

    private $getFileRepository;

    public function __construct(GetFileInterface $getFileRepository)
    {
        $this->getFileRepository = $getFileRepository;
    }

    //M E T H O D E ======================
    // core
    public function getFile(Request $request)
    {
        return $this->getFileRepository->getFile($request);
    }
}
