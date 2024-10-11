<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\Agenda\AgendaInterface as AgendaInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Http\Requests\AgendaRequest;
use App\Models\Agenda;
use App\Repositories\Agenda\AgendaRepository;

class AgendaController extends Controller
{

    private $agendaRepository;

    public function __construct(AgendaInterface $agendaRepository)
    {
        $this->agendaRepository = $agendaRepository;
    }

    //M E T H O D E ======================
    // core
    public function index(Request $request)
    {
        return $this->agendaRepository->getAgendas($request);
    }

    // create
    public function insert(Request $request)
    {
        return $this->agendaRepository->createAgenda($request);
    }

    // update
    public function edit(Request $request, $id)
    {
        return $this->agendaRepository->updateAgenda($request, $id);
    }

    // delete
    public function drop($id)
    {
        return $this->agendaRepository->deleteAgenda($id);
    }
}
