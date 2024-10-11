<?php

namespace App\Repositories\Agenda;

use App\Http\Requests\AgendaRequest;

use Illuminate\Http\Request;

interface AgendaInterface
{
    // getAll
    public function getAgendas($request);
    // insertData
    public function createAgenda($request);
    // update
    public function updateAgenda($request, $id);
    // delete
    public function deleteAgenda($id);
}
