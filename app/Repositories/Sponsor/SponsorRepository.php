<?php

namespace App\Repositories\Sponsor;

use App\Helpers\Helper;
use App\Models\Sponsor;
use App\Models\Event_Program;
use App\Repositories\Sponsor\SponsorInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class SponsorRepository implements SponsorInterface
{
    private $sponsor;
    // 1 Minute redis expired
    private $expired = 3600;
    private $generalRedisKeys = 'Sponsor-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Sponsor $sponsor)
    {
        $this->sponsor = $sponsor;
    }

    public function getSponsor($request)
    {
        try {


            $limit = Helper::limitDatas($request);

            if (($request->order != null) or ($request->order != "")) {
                $order = $request->order == "desc" ? "desc" : "asc";
            } else {
                $order = "desc";
            }
            $getSearch = $request->search;
            $getById = $request->id;
            $getEvent = $request->event;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Event=" . $getEvent .  ",#Search=" . $getSearch;

            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Sponsor By {$params} from (CACHE)", $result);
            }

            $query = Sponsor::with(['event'])->orderBy('created_at', $order);

            if ($request->filled('event') && $request->event !== "") {
                $query->whereHas('event', function ($queryEvent) use ($request) {
                    // return $queryEvent->where('id', Str::slug($request->event));
                    return $queryEvent->where('event_id', $request->event);
                });
                // $query->where('event.slug',  $getEvent);
            }


            if ($request->filled('search')) {
                $query->where('title_sponsor', 'LIKE', '%' . $getSearch . '%');
            }

            if ($request->filled('id')) {
                $query->where('id', $getById);
            }


            if ($request->filled('paginate') && $paginate == "true") {
                $setPaginate = true;
                $result = $query->paginate($limit);
            } else {

                $setPaginate = false;
                $result = $query->limit($limit)->get();
            }


            $datas = Self::queryGetModify($result, $setPaginate, true);
            Redis::set($key, json_encode($datas));
            Redis::expire($key,  $this->expired);
            return $this->success("List Sponsor By {$params}", $datas);
            // }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", "");
        }
        // }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'title_sponsor'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {

            $event_id = Event_Program::find($request->event_id);
            // check
            if (!$event_id) {
                return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
            }

            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }

            $fileName = $request->hasFile('image') ? "sponsor_" . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension() : "";

            $data = [
                'title_sponsor' => $request->title_sponsor,
                'image' => $fileName,
                'event_id' => $request->filled('event_id') ? $request->event_id : null,
                'created_by' => Auth::user()->id,

            ];
            // Create Pencapaian
            $add = Sponsor::create($data);

            if ($add) {

                Helper::saveImage('image', $fileName, $request, $this->destinationImage);

                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Sponsor Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Sponsor gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title_sponsor'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {

            $datas = Sponsor::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Pencapaian dengan ID = ($id) tidak ditemukan!", 404);
            }

            // search
            $event_program = Event_Program::find($request->event_id);

            // checkID
            if (!$event_program) {
                return $this->error("Not Found", "Acara dengan ID = ($id) tidak ditemukan!", 404);
            }

            if ($request->filled('event_id') && $request->event_id !== "") {
                $event_id = Event_Program::find($request->event_id);
                // check
                if (!$event_id) {
                    return $this->error("Not Found", "Event dengan ID = ($request->event_id) tidak ditemukan!", 404);
                }
            }
            $datas['title_sponsor'] = $request->title_sponsor;
            $datas['event_id'] = $request->event_id;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('image')) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                // Image name
                $fileName = 'sponsor_' . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension();
                $datas['image'] = $fileName;

                // Image save in public folder
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
            } else {
                if ($request->delete_image) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                    $datas['image'] = null;
                }
                $datas['image'] = $datas->image;
            }

            // update datas
            if ($datas->save()) {
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Sponsor Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Sponsor gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }



    public function delete($id)
    {
        try {

            $data = Sponsor::find($id);
            if (!$data) {
                return $this->error("Not Found", "Sponsor dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old image delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);

                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Sponsor dengan ID = ($id) Berhasil dihapus permanen!");
            }
            return $this->error("FAILED", "Sponsor dengan ID = ($id) Gagal dihapus permanen!", 400);
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

        unset($item->createdBy, $item->editedBy);

        return $item;
    }
    function checkLogin()
    {
        return !Auth::check() ? "-public-" : "-admin-";
    }
}
