<?php

namespace App\Repositories\Achievement;

use App\Helpers\Helper;
use App\Models\CategoryAchievement;
use App\Models\Achievement;
use App\Models\Event_Program;
use App\Models\Wilayah\Kabupaten;
use App\Repositories\Achievement\AchievementInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class AchievementRepository implements AchievementInterface
{
    private $achievement;
    // 1 Minute redis expired
    private $expired = 3600;
    private $generalRedisKeys = 'Achievement-';
    private $destinationFile = "files";
    use API_response;

    public function __construct(Achievement $achievement)
    {
        $this->achievement = $achievement;
    }

    public function getAchievement($request)
    {
        try {

            $limit = Helper::limitDatas($request);

            if (($request->order != null) or ($request->order != "")) {
                $order = $request->order == "desc" ? "desc" : "asc";
            } else {
                $order = "desc";
            }
            $getSearch = $request->search;
            $getRead = $request->read;
            $getById = $request->id;
            $getByKab = $request->kabupaten;
            $getTrash = $request->trash;
            $getEvent = $request->event;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Kabupaten=" . $getByKab . ",#Event=" . $getEvent . ",#Read=" . $getRead . ",#Search=" . $getSearch;

            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Pencapaian By {$params} from (CACHE)", $result);
            }

            if ($request->filled('trash') && $request->trash == "true") {
                $query = Achievement::onlyTrashed()->with(['event', 'kabupaten', 'contest', 'entrant'])->orderBy('created_at', $order);
            } else {
                $query = Achievement::with(['event', 'kabupaten', 'contest', 'entrant'])->orderBy('created_at', $order);
            }

            if ($request->filled('event') && $request->event !== "") {
                $query->whereHas('event', function ($queryEvent) use ($request) {
                    return $queryEvent->where('slug', Str::slug($request->event));
                });
                // $query->where('event.slug',  $getEvent);
            }

            if ($request->filled('kabupaten') && $request->kabupaten !== "") {
                $query->whereHas('kabupaten', function ($queryKabupaten) use ($request) {
                    return $queryKabupaten->where('id', $request->kabupaten);
                });
                // $query->where('event.slug',  $getEvent);
            }

            if ($request->filled('contest') && $request->contest !== "") {
                $query->whereHas('contest', function ($queryContest) use ($request) {
                    return $queryContest->where('slug', Str::slug($request->contest));
                });
                // $query->where('event.slug',  $getEvent);
            }


            if ($request->filled('search')) {
                $query->where('title_achievement', 'LIKE', '%' . $getSearch . '%');
            }

            if ($request->filled('read')) {
                $query->where('slug', $getRead);
            }

            if ($request->filled('id')) {
                $query->where('id', $getById);

                // return self::getById($getById);
            }


            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {
                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }

            $datas = Self::queryGetModify($result, $setPaginate, true);
            // if (!empty($datas)) {
            //     if (!Auth::check()) {
            //         $hidden = ['id'];
            //         $datas->makeHidden($hidden);
            //     }
            Redis::set($key, json_encode($datas));
            Redis::expire($key,  $this->expired);
            return $this->success("List Pencapaian By {$params}", $datas);
            // }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", "");
        }
        // }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'title_achievement'     => 'required',
            'document'           => 'mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:10000',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            // $kabupaten_id = Kabupaten::find($request->kabupaten_id);
            // // check
            // if (!$kabupaten_id) {
            //     return $this->error("Not Found", "Kabupaten dengan ID = ($request->kabupaten_id) tidak ditemukan!", 404);
            // }

            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('kab_id') && $request->kab_id !== "") {
                $kab_id = Kabupaten::find($request->kab_id);
                // check
                if (!$kab_id) {
                    return $this->error("Not Found", "Kabupaten dengan ID = ($request->kab_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('contest_id') && $request->contest_id !== "") {
                $contest_id = Event_Program::find($request->contest_id);
                // check
                if (!$contest_id) {
                    return $this->error("Not Found", "Contest dengan ID = ($request->contest_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('entrant_id') && $request->entrant_id !== "") {
                $entrant_id = Event_Program::find($request->entrant_id);
                // check
                if (!$entrant_id) {
                    return $this->error("Not Found", "Entrant dengan ID = ($request->entrant_id) tidak ditemukan!", 404);
                }
            }

            $fileName = $request->hasFile('document') ? "achievement_" . time() . "-" . Str::slug($request->document->getClientOriginalName()) . "." . $request->document->getClientOriginalExtension() : "";

            $data = [
                'title_achievement' => $request->title_achievement,
                'slug' => Str::slug($request->title_achievement),
                'title_evidence' => $request->title_evidence,
                'quantity_evidence' => $request->quantity_evidence,
                'document' => $fileName,
                'kab_id' => $request->filled('kab_id') ? $request->kab_id : null,
                'event_id' => $request->filled('event_id') ? $request->event_id : null,
                'contest_id' => $request->filled('contest_id') ? $request->contest_id : null,
                'entrant_id' => $request->filled('entrant_id') ? $request->entrant_id : null,
                'created_by' => Auth::user()->id,

            ];
            // Create Pencapaian
            $add = Achievement::create($data);

            if ($add) {
                // Storage::disk(['public' => 'achievement'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder achievement
                Helper::saveFile('document', $fileName, $request, $this->destinationFile);
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Pencapaian Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Pencapaian gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title_achievement'     => 'required',
            'document'           => 'mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx|max:10000',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {
            // $category_id = CategoryAchievement::find($request->category_id);
            // // check
            // if (!$category_id) {
            //     return $this->error("Not Found", "Category Pencapaian dengan ID = ($request->category_id) tidak ditemukan!", 404);
            // }
            // search
            $datas = Achievement::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Pencapaian dengan ID = ($id) tidak ditemukan!", 404);
            }
            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('kab_id') && $request->kab_id !== "") {
                $kab_id = Kabupaten::find($request->kab_id);
                // check
                if (!$kab_id) {
                    return $this->error("Not Found", "Kabupaten dengan ID = ($request->kab_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('contest_id') && $request->contest_id !== "") {
                $contest_id = Event_Program::find($request->contest_id);
                // check
                if (!$contest_id) {
                    return $this->error("Not Found", "Contest dengan ID = ($request->contest_id) tidak ditemukan!", 404);
                }
            }

            if ($request->filled('entrant_id') && $request->entrant_id !== "") {
                $entrant_id = Event_Program::find($request->entrant_id);
                // check
                if (!$entrant_id) {
                    return $this->error("Not Found", "Entrant dengan ID = ($request->entrant_id) tidak ditemukan!", 404);
                }
            }
            $datas['title_achievement'] = $request->title_achievement;
            $datas['slug'] = Str::slug($request->title_achievement);
            $datas['title_evidence'] = $request->title_evidence;
            $datas['quantity_evidence'] = $request->quantity_evidence;
            $datas['kab_id'] = $request->kab_id;
            $datas['event_id'] = $request->event_id;
            $datas['contest_id'] = $request->contest_id;
            $datas['entrant_id'] = $request->entrant_id;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('document')) {
                // Old iamge delete
                Helper::deleteFile($this->destinationFile,  $datas->document);

                // Image name
                $fileName = 'achievement_' . time() . "-" . Str::slug($request->document->getClientOriginalName()) . "." . $request->document->getClientOriginalExtension();
                $datas['document'] = $fileName;

                // Image save in public folder
                Helper::saveFile('image', $fileName, $request, $this->destinationFile);
            } else {
                if ($request->delete_document) {
                    // Old image delete
                    Helper::deleteFile($this->destinationFile,  $datas->document);

                    $datas['dccument'] = null;
                }
                $datas['document'] = $datas->document;
            }

            // update datas
            if ($datas->save()) {
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Pencapaian Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Pencapaian gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Achievement::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Pencapaian dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            if ($data->delete()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Pencapaian dengan ID = ($id) Berhasil dihapus!");
            }

            return $this->error("FAILED", "Pencapaian dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {

            $data = Achievement::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "Pencapaian dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old image delete
                Helper::deleteFile($this->destinationFile,  $data->image);
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Pencapaian dengan ID = ($id) Berhasil dihapus permanen!");
            }
            return $this->error("FAILED", "Pencapaian dengan ID = ($id) Gagal dihapus permanen!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Achievement::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Pencapaian Berhasil!");
            }
            return $this->error("FAILED", "Restore Pencapaian Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Achievement::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Pencapaian dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Pencapaian dengan ID = ($id) Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    function queryGetModify($datas, $paginate, $manyResult = false)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');

                $modifiedData = array_map(function ($item) {
                    // $item->berita_link = env('NEWS_LINK') . $item->slug;
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
                // return $datas;
                self::modifyData($datas);
            }
            return $datas;
        }
    }

    function modifyData($item)
    {

        // $category_id = [
        //     'id' => $item['category_id'],
        //     'name' => self::queryGetCategory($item['category_id'])->title_category,
        //     'slug' => self::queryGetCategory($item['category_id'])->slug,
        // ];
        // $item->category_id = $category_id;

        // $user_id = [
        //     'name' => Helper::queryGetUser($item['user_id']),
        // ];
        // $item->user_id = $user_id;
        // $item->image = Helper::convertImageToBase64('images/', $item->image);
        // $item = Helper::queryGetUserModify($item);
        $item->created_by = optional($item->createdBy)->only(['id', 'name']);
        $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

        unset($item->createdBy, $item->editedBy, $item->deleted_at);

        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }
}
