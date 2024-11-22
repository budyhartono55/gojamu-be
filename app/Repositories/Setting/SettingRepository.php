<?php

namespace App\Repositories\Setting;

use App\Helpers\Helper;
use App\Models\Setting;
use App\Repositories\Setting\SettingInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class SettingRepository implements SettingInterface
{
    private $setting;
    // 1 hour redis expired
    private $expired = 3600;
    private $generalRedisKeys = 'setting-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
    }


    public function getAll($request)
    {
        try {

            // Step 1: Get limit from helper or set default
            $limit = Helper::limitDatas($request);

            // Step 2: Determine order direction (asc/desc)
            $order = ($request->order && in_array($request->order, ['asc', 'desc'])) ? $request->order : 'desc';

            $getById = $request->id;
            $page = $request->page;
            $paginate = $request->paginate;


            $params = "#id=" . $getById .  ",#Paginate=" . $paginate . ",#Order=" . $order . ",#Limit=" . $limit .  ",#Page=" . $page;

            $keyOne = $this->generalRedisKeys . "All" . request()->get('page', 1) . "#params" . $params;

            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data Setting from (CACHE)", $result);
            }
            $datas = Setting::first();

            if ($request->filled('id')) {
                $datas->where('id',  $getById);
            }
            $data = $this->queryModifyUserForDatas($datas);

            // if (!Auth::check() and $datas) {
            //     $hidden = ['id'];
            //     $data->makeHidden($hidden);
            // }
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data Setting", $data);

            // $data = Setting::latest('created_at')->paginate(10);

            // return $this->success(
            //     " List semua data Setting",
            //     $data
            // );
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }


    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'image_jumbotron'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image1_app'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image2_app'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image3_app'  => 'image|mimes:jpeg,png,jpg|max:5012',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $datas = Setting::first();
            if ($datas) {
                return $this->error("FAILED", "Data Setting sudah ada!", 400);
            }
            $image_jumbotron = $request->hasFile('image_jumbotron') ? 'image_jumbotron_' . time() . "." . $request->image_jumbotron->getClientOriginalExtension() : "";
            $image1_app = $request->hasFile('image1_app') ? 'image1_app_' . time() . "." . $request->image1_app->getClientOriginalExtension() : "";
            $image2_app = $request->hasFile('image2_app') ? 'image2_app_' . time() . "." . $request->image2_app->getClientOriginalExtension() : "";
            $image3_app = $request->hasFile('image3_app') ? 'image3_app_' . time() . "." . $request->image3_app->getClientOriginalExtension() : "";


            $data = [
                'image_jumbotron' => $image_jumbotron,
                'image1_app' => $image1_app,
                'image2_app' => $image2_app,
                'image3_app' => $image3_app,
                'title_jumbotron' => $request->title_jumbotron,
                'moto_jumbotron' => $request->moto_jumbotron,
                'title_app' => $request->title_app,
                'about_app' => $request->about_app,
                'address_app' => $request->address_app,
                'contact_app' => $request->contact_app,
                'facebook_app' => $request->facebook_app,
                'instagram_app' => $request->instagram_app,
                'title_promote' => $request->title_promote,
                'link_promote' => $request->link_promote,
                'created_by' => Auth::user()->id
            ];
            // Create Setting
            $add = Setting::create($data);

            if ($add) {

                // Save Image in Storage folder setting
                Helper::saveImage('image_jumbotron', $image_jumbotron, $request, $this->destinationImage);
                Helper::saveImage('image1_app', $image1_app, $request, $this->destinationImage);
                Helper::saveImage('image2_app', $image2_app, $request, $this->destinationImage);
                Helper::saveImage('image3_app', $image3_app, $request, $this->destinationImage);

                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("Setting Berhasil ditambahkan!", $data);
            }
            return $this->error("FAILED", "Setting gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!" . $e->getMessage(), $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        // return "$request->title_jumbotron";
        $validator = Validator::make($request->all(), [
            'image_jumbotron'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image1_app'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image2_app'  => 'image|mimes:jpeg,png,jpg|max:5012',
            'image3_app'  => 'image|mimes:jpeg,png,jpg|max:5012',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {
            // search
            $datas = Setting::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "Setting dengan ID = ($id) tidak ditemukan!", 404);
            }
            $image_jumbotron = $request->hasFile('image_jumbotron') ? 'image_jumbotron_' . time() . "." . $request->image_jumbotron->getClientOriginalExtension() : "";
            $image1_app = $request->hasFile('image1_app') ? 'image1_app_' . time() . "." . $request->image1_app->getClientOriginalExtension() : "";
            $image2_app = $request->hasFile('image2_app') ? 'image2_app_' . time() . "." . $request->image2_app->getClientOriginalExtension() : "";
            $image3_app = $request->hasFile('image3_app') ? 'image3_app_' . time() . "." . $request->image3_app->getClientOriginalExtension() : "";

            $datas['title_jumbotron'] = $request->title_jumbotron ?: $datas->title_jumbotron;
            $datas['moto_jumbotron'] = $request->moto_jumbotron ?: $datas->moto_jumbotron;
            $datas['title_app'] = $request->title_app ?: $datas->title_app;
            $datas['about_app'] = $request->about_app ?: $datas->about_app;
            $datas['address_app'] = $request->address_app ?: $datas->address_app;
            $datas['contact_app'] = $request->contact_app ?: $datas->contact_app;
            $datas['facebook_app'] = $request->facebook_app ?: $datas->facebook_app;
            $datas['instagram_app'] = $request->instagram_app ?: $datas->instagram_app;
            $datas['title_promote'] = $request->title_promote ?: $datas->title_promote;
            $datas['link_promote'] = $request->link_promote ?: $datas->link_promote;

            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('image_jumbotron')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_jumbotron);
                // public storage
                $datas['image_jumbotron'] = $image_jumbotron;
                Helper::saveImage('image_jumbotron', $image_jumbotron, $request, $this->destinationImage);
            } else {
                if ($request->delete_image_jumbotron) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image_jumbotron);

                    $datas['image_jumbotron'] = null;
                }
                $datas['image_jumbotron'] = $datas->image_jumbotron;
            }

            if ($request->hasFile('image1_app')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image1_app);
                // public storage
                $datas['image1_app'] = $image1_app;
                Helper::saveImage('image1_app', $image1_app, $request, $this->destinationImage);
            } else {
                if ($request->delete_image1_app) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image1_app);

                    $datas['image1_app'] = null;
                }
                $datas['image1_app'] = $datas->image1_app;
            }

            if ($request->hasFile('image2_app')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image2_app);
                // public storage
                $datas['image2_app'] = $image2_app;
                Helper::saveImage('image2_app', $image2_app, $request, $this->destinationImage);
            } else {
                if ($request->delete_image2_app) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image2_app);

                    $datas['image2_app'] = null;
                }
                $datas['image2_app'] = $datas->image2_app;
            }

            if ($request->hasFile('image3_app')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image3_app);
                // public storage
                $datas['image3_app'] = $image3_app;
                Helper::saveImage('image3_app', $image3_app, $request, $this->destinationImage);
            } else {
                if ($request->delete_image3_app) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image3_app);

                    $datas['image3_app'] = null;
                }
                $datas['image3_app'] = $datas->image3_app;
            }

            // update datas
            if ($datas->save()) {
                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("Setting Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "Setting Gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = Setting::find($id);
            if (!$data) {
                return $this->error("Not Found", "Setting dengan ID = ($id) tidak ditemukan!", 404);
            }


            // approved
            if ($data->delete()) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image_jumbotron);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image1_app);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image2_app);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image3_app);


                Helper::deleteRedis($this->generalRedisKeys . "*");
                return $this->success("COMPLETED", "Setting dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "Setting dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    static function queryModifyUserForDatas($datas, $manyResult = false, $paginate = true)
    {
        if ($datas) {
            if ($manyResult) {

                $modifiedData = $paginate ? $datas->items() : data_get($datas, '*');

                $modifiedData = array_map(function ($item) {

                    self::queryGetUserModify($item);
                    return $item;
                }, $modifiedData);
            } else {
                self::queryGetUserModify($datas);
            }
            return $datas;
        }
    }

    static function queryGetUserModify($item)
    {
        $item->created_by = optional($item->createdBy)->only(['id', 'name']);
        $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

        unset($item->createdBy, $item->editedBy);
        return $item;
    }
}
