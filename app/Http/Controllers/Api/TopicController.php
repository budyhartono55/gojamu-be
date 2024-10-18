<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Topic\TopicInterface as TopicInterface;


class TopicController extends Controller
{

    private $topicRepository;

    public function __construct(TopicInterface $topicRepository)
    {
        $this->topicRepository = $topicRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->topicRepository->getAll($request);
    }

    //findOne
    // public function findById($id)
    // {
    //     return $this->topicRepository->findById($id);
    // }

    // create
    public function add(Request $request)
    {
        return $this->topicRepository->create($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->topicRepository->update($request, $id);
    }

    // delete
    public function delete($id)
    {
        return $this->topicRepository->delete($id);
    }
}
