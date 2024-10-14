<?php

namespace App\Repositories\Event_Program;

use App\Repositories\Event_Program\Event_ProgramInterface as Event_ProgramInterface;
use App\Models\Event_Program;
use App\Models\User;
use App\Http\Resources\Event_ProgramResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Event_ProgramRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\Achievement;
use App\Models\Agenda;
use App\Models\Announcement;
use Carbon\Carbon;
use App\Models\Ctg_Event_Program;
use App\Models\Base;
use App\Models\Contest;
use App\Models\Entrant;
use App\Models\Liaison;
use App\Models\News;
use App\Models\Sponsor;
use App\Models\Wilayah\Kabupaten;
use App\Models\Wilayah\Kecamatan;
use Intervention\Image\Facades\Image;

class Event_ProgramRepository implements Event_ProgramInterface
{

    protected $event_program;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Event_Program $event_program)
    {
        $this->event_program = $event_program;
        $this->generalRedisKeys = "event_";
    }

    // getAll
    public function getEvent_Programs($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        $getSlug = $request->slug;

        if (!empty($getSlug)) {
            return self::showBySlug($getSlug);
        } elseif (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllEvent_Programs();
        }
    }

    //allEvent
    public function getAllEvent_Programs()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Acara", $result);
            }

            $event_program = Event_Program::with(['createdBy', 'editedBy'])
                ->latest('created_at')
                ->paginate(12);

            if ($event_program) {
                $modifiedData = $event_program->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($event_program));
                return $this->success("List keseluruhan Acara", $event_program);
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
                return $this->success("(CACHE): Detail Acara dengan slug = ($slug)", $result);
            }

            $slug = Str::slug($slug);
            $event_program = Event_Program::where('slug', $slug)
                ->latest('created_at')
                ->first();

            if ($event_program) {
                $totalParticipants = Entrant::where('event_id', $event_program->id)->count();
                $event_program->total_participants = $totalParticipants;

                $bases = Base::where('event_id', $event_program->id)->get();
                $groupedBases = [];
                foreach ($bases as $base) {
                    $kabupaten = Kabupaten::where('id', $base->asal_kab_id)->first();
                    // dd($kabupaten);
                    if ($kabupaten) {
                        $kabupatenName = $kabupaten->nama;
                        if (!isset($groupedBases[$kabupatenName])) {
                            $groupedBases[$kabupatenName] = [];
                        }
                        $groupedBases[$kabupatenName][] = $base;
                    }
                }
                $event_program->bases = $groupedBases;

                $createdBy = User::select('name')->find($event_program->created_by);
                $editedBy = User::select('name')->find($event_program->edited_by);
                $event_program->created_by = optional($createdBy)->only(['name']);
                $event_program->edited_by = optional($editedBy)->only(['name']);

                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($event_program));
                return $this->success("Detail Acara dengan slug = ($slug)", $event_program);
            } else {
                return $this->error("Not Found", "Acara dengan slug = ($slug) tidak ditemukan!", 404);
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
                return $this->success("(CACHE): Detail Acara dengan ID = ($id)", $result);
            }

            $event_program = Event_Program::find($id);
            if ($event_program) {
                $totalParticipants = Entrant::where('event_id', $id)->count();
                $event_program->total_participants = $totalParticipants;

                $bases = Base::where('event_id', $id)->get();
                $contest = Contest::where('event_id', $id)->get();
                $groupedBases = [];
                foreach ($bases as $base) {
                    $kabupaten = Kabupaten::where('id', $base->asal_kab_id)->first();
                    // dd($kabupaten);
                    if ($kabupaten) {
                        $kabupatenName = $kabupaten->nama;
                        if (!isset($groupedBases[$kabupatenName])) {
                            $groupedBases[$kabupatenName] = [];
                        }
                        $groupedBases[$kabupatenName][] = $base;
                    }
                }
                $event_program->contest = $contest;
                $event_program->bases = $groupedBases;
                //setJumlahParticipantContest
                foreach ($contest as $c) {
                    $memQuantity = Entrant::where('contest_id', $c->id)->count();
                    $c->mem_quantity = $memQuantity;
                }

                $createdBy = User::select('name')->find($event_program->created_by);
                $editedBy = User::select('name')->find($event_program->edited_by);
                $event_program->created_by = optional($createdBy)->only(['name']);
                $event_program->edited_by = optional($editedBy)->only(['name']);

                $key = Auth::check() ? $key : $key;
                Redis::setex($key, 60, json_encode($event_program));
                return $this->success("Detail Acara dengan ID = ($id)", $event_program);
            } else {
                return $this->error("Not Found", "Acara dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createEvent_Program($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_event' =>  'required',
                'banner'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
                'venue_img'       =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'title_event.required' => 'Mohon masukkan judul acara!',
                'banner.image' => 'Pastikan file foto bertipe gambar',
                'banner.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'banner.max' => 'File Banner terlalu besar, usahakan dibawah 3MB',
                'venue_img.image' => 'Pastikan file foto bertipe gambar',
                'venue_img.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'venue_img.max' => 'File Gambar Venue terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $event_program = new Event_Program();
            $event_program->title_event = $request->title_event;
            $event_program->description = $request->description;
            $event_program->location = $request->location;
            $event_program->url_location = $request->url_location;
            $event_program->guide_book = $request->guide_book;
            $event_program->agenda = $request->agenda;
            $event_program->start_date = Carbon::createFromFormat('d-m-Y', $request->start_date);
            $event_program->end_date = Carbon::createFromFormat('d-m-Y', $request->end_date);

            $event_program->slug = Str::slug($request->title_event, '-');
            $checkEvent = Event_Program::where('slug', $event_program->slug)->first();
            if ($checkEvent) {
                return $this->error('Terjadi Kesalahan', 'Nama Acara yang anda masukkan telah terdaftar pada database kami, mohon masukkan nama Acara lain.', 400);
            }
            if ($request->hasFile('banner')) {
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $banner = $request->file('banner');
                $imageName = $event_program->slug . "-" . time() . "." . $banner->getClientOriginalExtension();

                $event_program->banner = $imageName;
                //storeOriginal
                $banner->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($banner, $imageName, $request);
            }

            if ($request->hasFile('venue_img')) {
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $venue_img = $request->file('venue_img');
                $imageName = "venue" . "-" . $event_program->slug . "-" . time() . "." . $venue_img->getClientOriginalExtension();

                $event_program->venue_img = $imageName;
                //storeOriginal
                $venue_img->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($venue_img, $imageName, $request);
            }
            $event_program->venue_desc = $request->venue_desc;

            $user = Auth::user();
            $event_program->created_by = $user->id;
            $event_program->edited_by = $user->id;

            $create = $event_program->save();
            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Acara Berhasil ditambahkan!", $event_program);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateEvent_Program($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_event' =>  'required',
                'banner'          =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
                'venue_img'       =>  'image|
                                    mimes:jpeg,png,jpg,gif,svg|
                                    max:3072',
            ],
            [
                'title_event.required' => 'Mohon masukkan judul acara!',
                'banner.image' => 'Pastikan file foto bertipe gambar',
                'banner.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'banner.max' => 'File Banner terlalu besar, usahakan dibawah 3MB',
                'venue_img.image' => 'Pastikan file foto bertipe gambar',
                'venue_img.mimes' => 'Format gambar yang diterima hanya jpeg, png, jpg, gif dan svg',
                'venue_img.max' => 'File Gambar Venue terlalu besar, usahakan dibawah 3MB',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            // search
            $event_program = Event_Program::find($id);

            // checkID
            if (!$event_program) {
                return $this->error("Not Found", "Acara dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($request->hasFile('banner')) {
                //checkImage
                if ($event_program->banner) {
                    Storage::delete('public/images/' . $event_program->banner);
                    Storage::delete('public/thumbnails/t_images/' . $event_program->banner);
                }
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $banner = $request->file('banner');
                $event_program->slug = Str::slug($request->title_event, '-');
                $imageName = $event_program->slug . "-" . time() . "." . $banner->getClientOriginalExtension();

                $event_program->banner = $imageName;
                //storeOriginal
                $banner->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($banner, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/images/' . $event_program->banner);
                    Storage::delete('public/thumbnails/t_images/' . $event_program->banner);
                    $event_program->banner = null;
                }
                $event_program->banner = $event_program->banner;
            }
            if ($request->hasFile('venue_img')) {
                //checkImage
                if ($event_program->venue_img) {
                    Storage::delete('public/images/' . $event_program->venue_img);
                    Storage::delete('public/thumbnails/t_images/' . $event_program->venue_img);
                }
                $destination = 'public/images';
                $t_destination = 'public/thumbnails/t_images';
                $venue_img = $request->file('venue_img');
                $event_program->slug = Str::slug($request->title_event, '-');
                $imageName = "venue" . "-" . $event_program->slug . "-" . time() . "." . $venue_img->getClientOriginalExtension();

                $event_program->venue_img = $imageName;
                //storeOriginal
                $venue_img->storeAs($destination, $imageName);

                // compress to thumbnail 
                Helper::resizeImage($venue_img, $imageName, $request);
            } else {
                if ($request->delete_image) {
                    Storage::delete('public/images/' . $event_program->venue_img);
                    Storage::delete('public/thumbnails/t_images/' . $event_program->venue_img);
                    $event_program->venue_img = null;
                }
                $event_program->venue_img = $event_program->venue_img;
            }


            // approved
            $event_program['title_event'] = $request->title_event ?? $event_program->title_event;
            $event_program['description'] = $request->description ?? $event_program->description;
            $event_program['location'] = $request->location ?? $event_program->location;
            $event_program['url_location'] = $request->url_location ?? $event_program->url_location;
            $event_program['guide_book'] = $request->guide_book ?? $event_program->guide_book;
            $event_program['agenda'] = $request->agenda ?? $event_program->agenda;
            $event_program['start_date'] = Carbon::createFromFormat('d-m-Y', $request->start_date) ?? $event_program->start_date;
            $event_program['end_date'] =  Carbon::createFromFormat('d-m-Y', $request->end_date) ?? $event_program->end_date;
            $event_program['venue_desc'] = $request->venue_desc ?? $event_program->venue_desc;
            $event_program['slug'] =  Str::slug($request->title_event, '-');

            $event_program['created_by'] = $event_program->created_by;
            $event_program['edited_by'] = Auth::user()->id;

            //save
            $update = $event_program->save();
            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Acara Berhasil diperbaharui!", $event_program);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // delete
    public function deleteEvent_Program($id)
    {
        try {
            $tables = [
                'Contest' => Contest::where('event_id', $id)->exists(),
                'Agenda' => Agenda::where('event_id', $id)->exists(),
                'Entrant' => Entrant::where('event_id', $id)->exists(),
                'Base' => Base::where('event_id', $id)->exists(),
                'Sponsor' => Sponsor::where('event_id', $id)->exists(),
                'Liaison' => Liaison::where('event_id', $id)->exists(),
                'Achievement' => Achievement::where('event_id', $id)->exists(),
                'Announcement' => Announcement::where('event_id', $id)->exists(),
                'News' => News::where('event_id', $id)->exists(),
                //sampah
                'LiaisonJunk' => Liaison::withTrashed()->where('event_id', $id)->exists(),
                'AchievementJunk' => Achievement::withTrashed()->where('event_id', $id)->exists(),
                'AnnouncementJunk' => Announcement::withTrashed()->where('event_id', $id)->exists(),
                'NewsJunk' => News::withTrashed()->where('event_id', $id)->exists()
            ];

            $usedInTables = [];
            foreach ($tables as $table => $exists) {
                if ($exists) {
                    $usedInTables[] = $table;
                }
            }
            if (!empty($usedInTables)) {
                $tablesList = implode(', ', $usedInTables);
                return $this->error("Failed", "Event dengan ID = ($id) digunakan di tabel: $tablesList", 400);
            }
            // search
            $event_program = Event_Program::find($id);
            if (!$event_program) {
                return $this->error("Not Found", "Acara dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($event_program->banner) {
                Storage::delete('public/images/' . $event_program->banner);
                Storage::delete('public/thumbnails/t_images/' . $event_program->banner);
            }
            if ($event_program->venue_img) {
                Storage::delete('public/images/' . $event_program->venue_img);
                Storage::delete('public/thumbnails/t_images/' . $event_program->venue_img);
            }
            // approved
            $del = $event_program->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Acara dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}
