<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Comment\CommentInterface as CommentInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\CommentRequest;
use App\Models\Comment;
use App\Repositories\Comment\CommentRepository;

class CommentController extends Controller
{

    private $commentRepository;

    public function __construct(CommentInterface $commentRepository)
    {
        $this->commentRepository = $commentRepository;
    }

    //M E T H O D E ======================
    // core
    // public function index(Request $request)
    // {
    //     return $this->commentRepository->getComments($request);
    // }
    // public function findById($id)
    // {
    //     return $this->commentRepository->findById($id);
    // }
    // create
    public function insert(Request $request)
    {
        return $this->commentRepository->createComment($request);
    }

    // update
    // public function edit(Request $request, $id)
    // {
    //     return $this->commentRepository->updateComment($request, $id);
    // }

    // // delete
    // public function drop($id)
    // {
    //     return $this->commentRepository->deleteComment($id);
    // }
}