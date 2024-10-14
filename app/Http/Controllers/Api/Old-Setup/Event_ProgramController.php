<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Event_Program\Event_ProgramInterface as Event_ProgramInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\Event_ProgramRequest;
use App\Models\Event_Program;
use App\Repositories\Event_Program\Event_ProgramRepository;

class Event_ProgramController extends Controller
{

    private $event_ProgramRepository;

    public function __construct(Event_ProgramInterface $event_ProgramRepository)
    {
        $this->event_ProgramRepository = $event_ProgramRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->event_ProgramRepository->getEvent_Programs($request);
    }

    // create
    public function insert(Request $request)
    {
        return $this->event_ProgramRepository->createEvent_Program($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->event_ProgramRepository->updateEvent_Program($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->event_ProgramRepository->deleteEvent_Program($id);
    }
}
