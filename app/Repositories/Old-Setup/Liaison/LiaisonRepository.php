<?php

namespace App\Repositories\Liaison;

use App\Helpers\Helper;
use App\Models\CategoryLiaison;
use App\Models\Event_Program;
use App\Models\Liaison;
use App\Models\Wilayah\Kabupaten;
use App\Repositories\Liaison\LiaisonInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class LiaisonRepository implements LiaisonInterface
{
    private $liaison;
    // 1 Minute redis expired
    private $expired = 3600;
    private $generalRedisKeys = 'Liaison-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Liaison $liaison)
    {
        $this->liaison = $liaison;
    }

    public function getLiaison($request)
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
            $getTrash = $request->trash;
            $getEvent = $request->event;
            $getKabupaten = $request->kabupaten;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById . ",#Trash=" . $getTrash . ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page . ",#Kabupaten=" . $getKabupaten . ",#Event=" . $getEvent . ",#Search=" . $getSearch;

            $key = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("List Liaison By {$params} from (CACHE)", $result);
            }

            if ($request->filled('trash') && $request->trash == "true") {
                $query = Liaison::onlyTrashed()->with(['event', 'kabupaten'])->orderBy('created_at', $order);
            } else {
                $query = Liaison::with(['event', 'kabupaten'])->orderBy('created_at', $order);
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


            if ($request->filled('search')) {
                $query->where('name', 'LIKE', '%' . $getSearch . '%');
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
            return $this->success("List Liaison By {$params}", $datas);
            // }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", "");
        }
        // }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'contact'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'kab_id'  => 'required',
            'event_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
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



            $fileName = $request->hasFile('image') ? "liaison_" . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension() : "";

            $data = [
                'name' => $request->name,
                'contact' => $request->contact,
                'gender' => $request->gender,
                'penanggung_jawab' => $request->penanggung_jawab,
                'image' => $fileName,
                'kab_id' => $request->filled('kab_id') ? $request->kab_id : null,
                'event_id' => $request->filled('event_id') ? $request->event_id : null,
                'created_by' => Auth::user()->id,
            ];
            // Create Liaison
            $add = Liaison::create($data);

            if ($add) {
                // Storage::disk(['public' => 'liaison'])->put($fileName, file_get_contents($request->image));
                // Save Image in Storage folder liaison
                Helper::saveImage('image', $fileName, $request, $this->destinationImage);
                // delete Redis when insert data
                Helper::deleteRedis($this->generalRedisKeys . "*");

                return $this->success("Liaison Berhasil ditambahkan!", $data,);
            }

            return $this->error("FAILED", "Liaison gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'contact'     => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
            'kab_id'  => 'required',
            'event_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
            // return response()->json($validator->errors(), 422);
        }
        try {

            // search
            $datas = Liaison::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Liaison dengan ID = ($id) tidak ditemukan!", 404);
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
            $datas['name'] = $request->name;
            $datas['contact'] = $request->contact;
            $datas['gender'] = $request->gender;
            $datas['penanggung_jawab'] = $request->penanggung_jawab;
            $datas['kab_id'] = $request->kab_id;
            $datas['event_id'] = $request->event_id;
            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('image')) {
                // Old iamge delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);

                // Image name
                $fileName = 'liaison_' . time() . "-" . Str::slug($request->image->getClientOriginalName()) . "." . $request->image->getClientOriginalExtension();
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

                return $this->success("Liaison Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Liaison gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Liaison::find($id);
            if (empty($data)) {
                return $this->error("Not Found", "Liaison dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            if ($data->delete()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Liaison dengan ID = ($id) Berhasil dihapus!");
            }

            return $this->error("FAILED", "Liaison dengan ID = ($id) gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function deletePermanent($id)
    {
        try {

            $data = Liaison::onlyTrashed()->find($id);
            if (!$data) {
                return $this->error("Not Found", "Liaison dengan ID = ($id) tidak ditemukan!", 404);
            }

                // approved
            ;
            if ($data->forceDelete()) {
                // Old image delete
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Liaison dengan ID = ($id) Berhasil dihapus permanen!");
            }
            return $this->error("FAILED", "Liaison dengan ID = ($id) Gagal dihapus permanen!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restore()
    {
        try {
            $data = Liaison::onlyTrashed();
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Liaison Berhasil!");
            }
            return $this->error("FAILED", "Restore Liaison Gagal!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function restoreById($id)
    {
        try {
            $data = Liaison::onlyTrashed()->where('id', $id);
            if ($data->restore()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Restore Liaison dengan ID = ($id) Berhasil!");
            }
            return $this->error("FAILED", "Restore Liaison dengan ID = ($id) Gagal!", 400);
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
