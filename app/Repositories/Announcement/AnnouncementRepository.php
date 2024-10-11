<?php

namespace App\Repositories\Announcement;

use App\Helpers\Helper;
use App\Models\Announcement;
use App\Models\Event_Program;
use App\Repositories\Announcement\AnnouncementInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class AnnouncementRepository implements AnnouncementInterface
{
    private $announcement;
    // 1 Minute redis expired
    private $expired = 3600;
    private $generalRedisKeys = 'Announcement-';
    private $destinationFile = "files";
    use API_response;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function get($request)
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
            $getTrash = $request->trash;
            $getEvent = $request->event;
            $getSlug = $request->slug;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Event=" . $getEvent . ",#Slug=" . $getSlug . ",#Search=" . $getSearch;

            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Pengumuman By {$params} from (CACHE)", $result);
            }

            if ($request->filled('trash') && $request->trash == "true") {
                $query = Announcement::onlyTrashed()->with('event')->orderBy('created_at', $order);
            } else {

                $query = Announcement::with(['event'])->orderBy('created_at', $order);
            }

            if ($request->filled('event') && $request->event !== "") {
                $query->whereHas('event', function ($queryEvent) use ($request) {
                    return $queryEvent->where('id', $request->event);
                });
            }


            if ($request->filled('search')) {
                $query->where('title_announcement', "like", "%{$getSearch}%");
            }



            if ($request->filled('read')) {
                $query->where('slug', $getRead);
                // return self::read($getRead, $order, $limit);
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
            Redis::set($this->generalRedisKeys, json_encode($datas));
            Redis::expire($this->generalRedisKeys,  $this->expired);
            return $this->success("List Pengumuman By {$params}", $datas);
            // }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
        // }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'title_announcement'     => 'required',
            'posted_at'     => 'required',
            'document'           => 'mimes:jpeg,png,jpg,gif,svg,pdf,xlsx,xls,doc,docx|max:10240',
            'posted_at' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        if ($request->filled('event_id') && $request->event_id !== "") {
            $event_id = Event_Program::find($request->event_id);
            // check
            if (!$event_id) {
                return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
            }
        }

        try {


            $fileName = $request->hasFile('document') ? "announcement_" . time() . "-" . Str::slug($request->document->getClientOriginalName()) . "." . $request->document->getClientOriginalExtension() : "";

            $data = [
                'title_announcement' => $request->title_announcement,
                'slug' => Str::slug($request->title_announcement),
                'description' => $request->description,
                'posted_at' => Carbon::createFromFormat('d-m-Y', $request->posted_at),
                'evidence' => $request->evidence,
                'url_location' => $request->url_location,
                'document' => $fileName,
                'event_id' => $request->filled('event_id') ? $request->event_id : null,
                'created_by' => Auth::user()->id,

            ];
            // Create Pengumuman
            $add = Announcement::create($data);

            if ($add) {
                // Storage::disk(['public' => 'announcement'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder announcement
                Helper::saveFile('document', $fileName, $request, $this->destinationFile);
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Pengumuman Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Pengumuman gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title_announcement'     => 'required',
            'posted_at'     => 'required',
            'document'           => 'mimes:jpeg,png,jpg,gif,svg,pdf,xlsx,xls,doc,docx|max:10240',
            'posted_at' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {

            // search
            $datas = Announcement::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Pengumuman dengan ID = ($id) tidak ditemukan!", 404);
            }

            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }
            $datas['title_announcement'] = $request->title_announcement;
            $datas['slug'] = Str::slug($request->title_announcement);
            $datas['description'] = $request->description;
            $datas['posted_at'] = Carbon::createFromFormat('d-m-Y', $request->posted_at);
            $datas['evidence'] = $request->evidence;
            $datas['url_location'] = $request->url_location;
            $datas['event_id'] = $request->event_id;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('document')) {
                // Old iamge delete
                Helper::deleteFile($this->destinationFile, $datas->document);

                // Image name
                $fileName = 'announcement_' . time() . "-" . Str::slug($request->document->getClientOriginalName()) . "." . $request->document->getClientOriginalExtension();
                $datas['document'] = $fileName;

                // Image save in public folder
                Helper::saveFile('document', $fileName, $request, $this->destinationFile);
            } else {
                if ($request->delete_document) {
                    // Old image delete
                    Helper::deleteFile($this->destinationFile, $datas->document);

                    $datas['document'] = null;
                }
                $datas['document'] = $datas->document;
            }

            // update datas
            if ($datas->save()) {
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Pengumuman Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Pengumuman gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Announcement::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Pengumuman dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            if ($data->delete()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Pengumuman dengan ID = ($id) Berhasil dihapus!");
            }

            return $this->error("FAILED", "Pengumuman dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {

            $data = Announcement::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "Pengumuman dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old image delete
                Helper::deleteFile($this->destinationFile, $data->document);
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Pengumuman dengan ID = ($id) Berhasil dihapus permanen!");
            }
            return $this->error("FAILED", "Pengumuman dengan ID = ($id) Gagal dihapus permanen!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Announcement::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Pengumuman Berhasil!");
            }
            return $this->error("FAILED", "Restore Pengumuman Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Announcement::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Pengumuman dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Pengumuman dengan ID = ($id) Gagal!", 400);
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
                    $item->berita_link = env('NEWS_LINK') . $item->slug;
                    self::modifyData($item);
                    return $item;
                }, $modifiedData);
            } else {
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

        unset($item->createdBy, $item->editedBy);
        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }
}
