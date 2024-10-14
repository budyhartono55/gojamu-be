<?php

namespace App\Repositories\Agenda;

use App\Repositories\Agenda\AgendaInterface as AgendaInterface;
use App\Models\Agenda;
use App\Models\User;
use App\Http\Resources\AgendaResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\AgendaRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Ctg_Agenda;
use App\Models\Event_Program;
use App\Models\Wilayah\Kecamatan;
use Intervention\Image\Facades\Image;

class AgendaRepository implements AgendaInterface
{

    protected $agenda;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Agenda $agenda)
    {
        $this->agenda = $agenda;
        $this->generalRedisKeys = "agenda_";
    }

    // getAll
    public function getAgendas($request)
    {
        if (($request->order != null) or ($request->order != "")) {
            $order = $request->order == "desc" ? "desc" : "asc";
        } else {
            $order = "desc";
        }

        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getEvent = $request->e_key;


        if (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllAgendas($getEvent, $order);
        }
    }

    public function getAllAgendas($event_id, $order)
    {
        try {

            $key = empty($event_id) ? $this->generalRedisKeys . "public_All_" . $order . request()->get("page", 1) : $this->generalRedisKeys . "public_All_" . $event_id . $order . request()->get("page", 1);
            $keyAuth = empty($event_id) ? $this->generalRedisKeys . "auth_All_" . $order . request()->get("page", 1) : $this->generalRedisKeys . "auth_All_" . $event_id . $order . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            $message = empty($event_id) ? "List keseluruhan Agenda" : "List Keseluruhan Agenda berdasarkan event_id = $event_id";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): $message", $result);
            }

            if (empty($event_id)) {
                $agenda = Agenda::with(['createdBy', 'editedBy', 'events'])
                    ->orderBy('created_at', $order)
                    ->paginate(12);
            } else {
                $agenda = Agenda::with(['createdBy', 'editedBy', 'events'])
                    ->where('event_id', $event_id)
                    ->orderBy('created_at', $order)
                    ->paginate(12);
            }
            if ($agenda->isNotEmpty()) {
                $modifiedData = $agenda->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->event_id = optional($item->events)->only(['id', 'title_event', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->events);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($agenda));
                return $this->success("$message", $agenda);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $id;
            $keyAuth = $this->generalRedisKeys . "auth_" . $id;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Agenda dengan ID = ($id)", $result);
            }

            $agenda = Agenda::find($id);
            if ($agenda) {
                $createdBy = User::select('name')->find($agenda->created_by);
                $editedBy = User::select('name')->find($agenda->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($agenda->event_id);

                $agenda->created_by = optional($createdBy)->only(['name']);
                $agenda->edited_by = optional($editedBy)->only(['name']);
                $agenda->event_id = optional($event)->only(['id', 'title_event', 'slug']);

                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($agenda));
                return $this->success("Detail Agenda dengan ID = ($id)", $agenda);
            } else {
                return $this->error("Not Found", "Agenda dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createAgenda($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_agenda' =>  'required',
            ],
            [
                'title_agenda.required' => 'Mohon masukkan nama agenda!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $agenda = new Agenda();
            $agenda->title_agenda = $request->title_agenda;
            $agenda->description = $request->description;
            $agenda->location = $request->location;
            $agenda->url_location = $request->url_location;
            $agenda->hold_at = Carbon::createFromFormat('d-m-Y', $request->hold_at);
            $agenda->event_id = $request->event_id;
            $agenda->slug = Str::slug($request->title_agenda, '-');

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $agenda->event_id = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", " Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            } else {
                $agenda->event_id = null;
            }

            $user = Auth::user();
            $agenda->created_by = $user->id;
            $agenda->edited_by = $user->id;

            $create = $agenda->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Agenda Berhasil ditambahkan!", $agenda);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateAgenda($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_agenda' =>  'required',
            ],
            [
                'title_agenda.required' => 'Mohon masukkan nama agenda!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            // search
            $agenda = Agenda::find($id);

            // checkID
            if (!$agenda) {
                return $this->error("Not Found", "Agenda dengan ID = ($id) tidak ditemukan!", 404);
            }

            // approved
            $agenda['title_agenda'] = $request->title_agenda ?? $agenda->title_agenda;
            $agenda['description'] = $request->description ?? $agenda->facility;
            $agenda['location'] = $request->location ?? $agenda->location;
            $agenda['url_location'] = $request->url_location ?? $agenda->url_location;
            $agenda['hold_at'] = Carbon::createFromFormat('d-m-Y', $request->hold_at) ?? $agenda->hold_at;
            $agenda['slug'] =  Str::slug($request->title_agenda, '-');

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $agenda['event_id']  = $event_id ?? $agenda->event_id;
                } else {
                    return $this->error("Tidak ditemukan!", " Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
                $delEvent = $request->delete_event;
                if ($delEvent) {
                    $agenda['event_id'] = null;
                }
            } else {
                $agenda['event_id']  = null;
            }

            $agenda['created_by'] = $agenda->created_by;
            $agenda['edited_by'] = Auth::user()->id;

            //save
            $update = $agenda->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Agenda Berhasil diperbaharui!", $agenda);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteAgenda($id)
    {
        try {
            // search
            $agenda = Agenda::find($id);
            if (!$agenda) {
                return $this->error("Not Found", "Agenda dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $agenda->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Agenda dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
