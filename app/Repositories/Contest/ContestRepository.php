<?php

namespace App\Repositories\Contest;

use App\Repositories\Contest\ContestInterface as ContestInterface;
use App\Models\Contest;
use App\Models\User;
use App\Http\Resources\ContestResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\ContestRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Achievement;
use App\Models\Ctg_Contest;
use App\Models\Entrant;
use App\Models\Event_Program;
use App\Models\Wilayah\Kecamatan;
use App\Models\Wilayah\Kabupaten;
use Illuminate\Validation\Rules\Exists;
use Intervention\Image\Facades\Image;

class ContestRepository implements ContestInterface
{

    protected $contest;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Contest $contest)
    {
        $this->contest = $contest;
        $this->generalRedisKeys = "contest_";
    }

    // getAll
    public function getContests($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getEvent = $request->e_key;
        $getSlug = $request->slug;
        $getPaginate = $request->paginate;
        $getKab = $request->asal_kab_id;
        $getName = $request->entrant_name;
        $getKeyword =  $request->search;

        // if (!empty($getEvent)) {
        // return self::getAllEntrantsByEvent($getEvent);
        if (!empty($getSlug)) {
            if (!empty($getKab) and !empty($getName)) {
                return self::showBySlugAccordKabAndName($getSlug, $getKab, $getName);
            }
            if (!empty($getKab)) {
                return self::showBySlugAccordKab($getSlug, $getKab);
            } elseif (!empty($getName)) {
                return self::showBySlugIncludeEntrantName($getSlug, $getName);
            }
            return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllContestByKeyword($getEvent, $getKeyword, $limit);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } elseif ($getPaginate == "FALSE" || $getPaginate == "false") {
            return self::getAllContestsUnpaginate($getEvent);
        } else {
            return self::getAllContests($getEvent);
        }
    }

    // paginate|grouped
    public function getAllContests($event_id)
    {
        try {
            $key = empty($event_id) ? $this->generalRedisKeys . "public_All_" . request()->get("page", 1) : $this->generalRedisKeys . "public_All_" . $event_id . request()->get("page", 1);
            $keyAuth = empty($event_id) ? $this->generalRedisKeys . "auth_All_" . request()->get("page", 1) : $this->generalRedisKeys . "auth_All_" . $event_id . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            $message = empty($event_id) ? "List keseluruhan Cabang Lomba" : "List Keseluruhan Cabang Lomba berdasarkan event_id = $event_id";
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): $message", $result);
            }

            if (empty($event_id)) {
                $contest = Contest::with(['createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->latest('created_at')
                    ->paginate(12);
            } else {
                $contest = Event_Program::find($event_id);
                if (!$contest) {
                    return $this->error("Acara tidak ditemukan!", "Acara dengan ID = ($event_id) tidak terdaftar pada database kami!", 404);
                }

                $contest = Contest::with(['createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->where('event_id', $event_id)
                    ->latest('created_at')
                    ->paginate(12);
            }

            if ($contest->isNotEmpty()) {
                $modifiedData = $contest->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->event_id = optional($item->events)->only(['id', 'title_event', 'slug']);
                    // $item->asal_kab_id = optional($item->kabupaten)->only(['nama']);
                    $item->mem_quantity = $item->entrants_count;

                    unset($item->createdBy, $item->editedBy, $item->events, $item->kabupaten, $item->entrants_count);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($contest));
                return $this->success("$message", $contest);
            } else {
                return $this->error("$message Tidak ditemukan!", [], 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // unpaginate|grouped
    public function getAllContestsUnpaginate($event_id)
    {
        try {
            $page = request()->get("page", 1);
            $key = empty($event_id) ? $this->generalRedisKeys . "public_All_" . $page : $this->generalRedisKeys . "public_All_" . $event_id . "_" . $page;
            $keyAuth = empty($event_id) ? $this->generalRedisKeys . "auth_All_" . $page : $this->generalRedisKeys . "auth_All_" . $event_id . "_" . $page;
            $key = Auth::check() ? $keyAuth : $key;
            $message = empty($event_id) ? "List keseluruhan Cabang Lomba" : "List Keseluruhan Cabang Lomba berdasarkan event_id = $event_id";

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): $message", $result);
            }

            if (empty($event_id)) {
                $contests = Contest::with(['createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->latest('created_at')
                    ->get();
            } else {
                $event = Event_Program::find($event_id);
                if (!$event) {
                    return $this->error("Acara tidak ditemukan!", "Acara dengan ID = ($event_id) tidak terdaftar pada database kami!", 404);
                }

                $contests = Contest::with(['createdBy', 'editedBy', 'events'])
                    ->withCount('entrants')
                    ->where('event_id', $event_id)
                    ->latest('created_at')
                    ->get();
            }

            if ($contests->isNotEmpty()) {
                $modifiedData = $contests->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->event_id = optional($item->events)->only(['id', 'title_event', 'slug']);
                    $item->mem_quantity = $item->entrants_count;

                    unset($item->createdBy, $item->editedBy, $item->events, $item->entrants_count);
                    return $item;
                });

                Redis::setex($key, 60, json_encode($modifiedData));
                return $this->success("$message", $modifiedData);
            } else {
                return $this->error("$message Tidak ditemukan!", [], 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllContestByKeyword($event_id, $keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $event_id;
            $keyAuth = $this->generalRedisKeys . "auth_" . $event_id;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Lomba dengan keyword = ($keyword) dengan event_id = ($event_id)", $result);
            }

            $contest = Contest::with(['createdBy', 'editedBy', 'events'])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_contest', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->where('event_id', $event_id)
                ->latest('created_at')
                ->get();

            if ($contest->isNotEmpty()) {
                $modifiedData = $contest->map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                });

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($modifiedData));

                return $this->success("List Keseluruhan Lomba berdasarkan keyword = ($keyword) dengan event_id = ($event_id)", $modifiedData);
            } else {
                return $this->error("Not Found", "Lomba dengan keyword = ($keyword) dengan event_id = ($event_id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function showBySlug($slug)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $slug;
            $keyAuth = $this->generalRedisKeys . "auth_" . $slug;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Cabang Lomba dengan slug = ($slug)", $result);
            }

            $slug = Str::slug($slug);
            $contest = Contest::with(['entrants.kabupaten'])->withCount('entrants')
                ->where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($contest) {
                $createdBy = User::select('name')->find($contest->created_by);
                $editedBy = User::select('name')->find($contest->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($contest->event_id);

                $contest->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $contest->mem_quantity = $contest->entrants_count;
                $contest->created_by = optional($createdBy)->only(['name']);
                $contest->edited_by = optional($editedBy)->only(['name']);

                foreach ($contest->entrants as $entrant) {
                    $entrant->asal_kab_id = [
                        'id' => $entrant->kabupaten->id ?? null,
                        'nama' => $entrant->kabupaten->nama ?? null,
                    ];
                    unset($entrant->kabupaten);
                }

                unset($contest->entrants_count);

                Redis::setex($key, 60, json_encode($contest));
                return $this->success("Detail Cabang Lomba dengan slug = ($slug)", $contest);
            } else {
                return $this->error("Not Found", "Cabang Lomba dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function showBySlugAccordKab($slug, $kabupaten)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $slug . ($kabupaten ? "_kab_$kabupaten" : "");
            $keyAuth = $this->generalRedisKeys . "auth_" . $slug . ($kabupaten ? "_kab_$kabupaten" : "");
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten)", $result);
            }

            $slug = Str::slug($slug);
            $contest = Contest::with(['entrants' => function ($query) use ($kabupaten) {
                if ($kabupaten) {
                    $query->where('asal_kab_id', $kabupaten);
                }
            }])
                ->withCount(['entrants' => function ($query) use ($kabupaten) {
                    if ($kabupaten) {
                        $query->where('asal_kab_id', $kabupaten);
                    }
                }])
                ->where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($contest) {
                $createdBy = User::select('name')->find($contest->created_by);
                $editedBy = User::select('name')->find($contest->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($contest->event_id);

                $contest->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $contest->mem_quantity = $contest->entrants_count;
                $contest->created_by = optional($createdBy)->only(['name']);
                $contest->edited_by = optional($editedBy)->only(['name']);

                foreach ($contest->entrants as $entrant) {
                    $entrant->asal_kab_id = [
                        'id' => $entrant->kabupaten->id ?? null,
                        'nama' => $entrant->kabupaten->nama ?? null,
                    ];
                    unset($entrant->kabupaten);
                }

                unset($contest->entrants_count);

                Redis::setex($key, 60, json_encode($contest));
                return $this->success("Detail Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten)", $contest);
            } else {
                return $this->error("Not Found", "Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function showBySlugIncludeEntrantName($slug, $e_name)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $e_name . $slug;
            $keyAuth = $this->generalRedisKeys . "auth_" . $e_name . $slug;
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Pencarian dengan nama $e_name pada Cabang Lomba $slug", $result);
            }

            $slug = Str::slug($slug);
            $contest = Contest::with(['entrants' => function ($query) use ($e_name) {
                if ($e_name) {
                    $query->where('name', 'LIKE', "%$e_name%");
                }
            }])
                ->withCount(['entrants' => function ($query) use ($e_name) {
                    if ($e_name) {
                        $query->where('name', 'LIKE', "%$e_name%");
                    }
                }])
                ->where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($contest) {
                $createdBy = User::select('name')->find($contest->created_by);
                $editedBy = User::select('name')->find($contest->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($contest->event_id);

                $contest->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $contest->mem_quantity = $contest->entrants_count;
                $contest->created_by = optional($createdBy)->only(['name']);
                $contest->edited_by = optional($editedBy)->only(['name']);

                foreach ($contest->entrants as $entrant) {
                    $entrant->asal_kab_id = [
                        'id' => $entrant->kabupaten->id ?? null,
                        'nama' => $entrant->kabupaten->nama ?? null,
                    ];
                    unset($entrant->kabupaten);
                }

                unset($contest->entrants_count);

                Redis::setex($key, 60, json_encode($contest));
                return $this->success("Pencarian dengan nama $e_name pada Cabang Lomba $slug", $contest);
            } else {
                return $this->error("Not Found", "Pencarian dengan nama $e_name pada Cabang Lomba $slug tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function showBySlugAccordKabAndName($slug, $kabupaten, $name)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . $slug . ($kabupaten ? "_kab_$kabupaten" : "") . ($name ? "_name_$name" : "");
            $keyAuth = $this->generalRedisKeys . "auth_" . $slug . ($kabupaten ? "_kab_$kabupaten" : "") . ($name ? "_name_$name" : "");
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Detail Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten) dan name = ($name)", $result);
            }

            $slug = Str::slug($slug);
            $contest = Contest::with(['entrants' => function ($query) use ($kabupaten, $name) {
                if ($kabupaten) {
                    $query->where('asal_kab_id', $kabupaten);
                }
                if ($name) {
                    $query->where('name', 'LIKE', "%$name%");
                }
            }])
                ->withCount(['entrants' => function ($query) use ($kabupaten, $name) {
                    if ($kabupaten) {
                        $query->where('asal_kab_id', $kabupaten);
                    }
                    if ($name) {
                        $query->where('name', 'LIKE', "%$name%");
                    }
                }])
                ->where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($contest) {
                $createdBy = User::select('name')->find($contest->created_by);
                $editedBy = User::select('name')->find($contest->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($contest->event_id);

                $contest->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $contest->mem_quantity = $contest->entrants_count;
                $contest->created_by = optional($createdBy)->only(['name']);
                $contest->edited_by = optional($editedBy)->only(['name']);

                foreach ($contest->entrants as $entrant) {
                    $entrant->asal_kab_id = [
                        'id' => $entrant->kabupaten->id ?? null,
                        'nama' => $entrant->kabupaten->nama ?? null,
                    ];
                    unset($entrant->kabupaten);
                }

                unset($contest->entrants_count);

                Redis::setex($key, 60, json_encode($contest));
                return $this->success("Detail Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten) dan nama = ($name)", $contest);
            } else {
                return $this->error("Not Found", "Cabang Lomba dengan slug = ($slug) filtering berdasarkan peserta dengan kabupaten ID = ($kabupaten) dan nama = ($name) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
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
                return $this->success("(CACHE): Detail Cabang Lomba dengan ID = ($id)", $result);
            }

            $contest = Contest::withCount('entrants')->find($id);
            if ($contest) {
                $entrants = Entrant::where('contest_id', $contest->id)->get();
                $groupedEntrant = [];

                foreach ($entrants as $entrant) {
                    $kabupaten = Kabupaten::where('id', $entrant->asal_kab_id)->first();
                    if ($kabupaten) {
                        $kabupatenName = $kabupaten->nama;
                        if (!isset($groupedEntrant[$kabupatenName])) {
                            $groupedEntrant[$kabupatenName] = [];
                        }
                        $groupedEntrant[$kabupatenName][] = $entrant;
                    }
                }
                $contest->entrants = $groupedEntrant;

                $createdBy = User::select('name')->find($contest->created_by);
                $editedBy = User::select('name')->find($contest->edited_by);
                $event = Event_Program::select('id', 'title_event', 'slug')->find($contest->event_id);

                $contest->event_id = optional($event)->only(['id', 'title_event', 'slug']);
                $contest->mem_quantity = $contest->entrants_count;
                $contest->created_by = optional($createdBy)->only(['name']);
                $contest->edited_by = optional($editedBy)->only(['name']);

                unset($contest->entrants_count);
                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($contest));
                return $this->success("Detail Cabang Lomba dengan ID = ($id)", $contest);
            } else {
                return $this->error("Not Found", "Cabang Lomba dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }


    // create
    public function createContest($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_contest' =>  'required',
                'event_id' =>  'required',
            ],
            [
                'title_contest.required' => 'Mohon masukkan nama lomba!',
                'event_id.required' => 'Masukkan Acara!',

            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $contest = new Contest();
            $contest->title_contest = $request->title_contest;
            $contest->description = $request->description;
            $contest->location = $request->location;
            $contest->url_location = $request->url_location;
            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $contest->event_id = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            }

            $contest->slug = Str::slug($request->title_contest, '-');
            $checkContest = Contest::where('slug', $contest->slug)->exists();
            if ($checkContest) {
                return $this->error('Terjadi Kesalahan', 'Nama Cabang Lomba yang anda masukkan telah terdaftar pada database kami, mohon masukkan nama Cabang Lomba lain.', 400);
            }

            $user = Auth::user();
            $contest->created_by = $user->id;
            $contest->edited_by = $user->id;

            $create = $contest->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Cabang Lomba Berhasil ditambahkan!", $contest);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateContest($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_contest' =>  'required',
            ],
            [
                'title_contest.required' => 'Mohon masukkan nama lomba!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 400);
        }
        try {
            // search
            $contest = Contest::find($id);

            // checkID
            if (!$contest) {
                return $this->error("Not Found", "Cabang Lomba dengan ID = ($id) tidak ditemukan!", 404);
            }

            // approved
            $contest['title_contest'] = $request->title_contest ?? $contest->title_contest;
            $contest['description'] = $request->description ?? $contest->description;
            $contest['location'] = $request->location ?? $contest->location;
            $contest['url_location'] = $request->url_location ?? $contest->url_location;
            // $contest['mem_quantity'] = $request->mem_quantity ?? $contest->mem_quantity;

            $event_id = $request->event_id;
            $event = Event_Program::where('id', $event_id)->first();
            if (!empty($event_id)) {
                if ($event) {
                    $contest['event_id'] = $event_id;
                } else {
                    return $this->error("Tidak ditemukan!", "Acara dengan ID = ($event_id) tidak ditemukan!", 404);
                }
            } else {
                $contest['event_id'] = $contest->event_id;
            }

            $contest['slug'] =  Str::slug($request->title_contest, '-');

            $contest['created_by'] = $contest->created_by;
            $contest['edited_by'] = Auth::user()->id;

            //save
            $update = $contest->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Cabang Lomba Berhasil diperbaharui!", $contest);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteContest($id)
    {
        try {

            $contest = Entrant::where('contest_id', $id)->exists() or Achievement::where('contest_id', $id)->exists();
            // $contestJunk = Entrant::withTrashed()->where('contest_id', $id)->exists();
            if ($contest) {
                return $this->error("Gagal!", "Contest dengan ID = ($id) digunakan di Entrant atau Achievement!", 400);
            }
            // search
            $contest = Contest::find($id);
            if (!$contest) {
                return $this->error("Not Found", "Cabang Lomba dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $del = $contest->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Cabang Lomba dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
