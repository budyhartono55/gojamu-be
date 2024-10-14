<?php

namespace App\Repositories\Event_Program;

use App\Http\Requests\Event_ProgramRequest;
use Illuminate\Http\Request;

interface Event_ProgramInterface
{
    // getAll
    public function getEvent_Programs($request);
    // insertData
    public function createEvent_Program($request);
    // update
    public function updateEvent_Program($request, $id);
    // delete
    public function deleteEvent_Program($id);
}
