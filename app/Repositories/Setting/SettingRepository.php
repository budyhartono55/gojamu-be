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

            $limit = Helper::limitDatas($request);

            if (($request->order != null) or ($request->order != "")) {
                $order = $request->order == "desc" ? "desc" : "asc";
            } else {
                $order = "desc";
            }
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
            'banner'  => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
            'banner_mobile'  => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
            'banner_tablet'  => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
            'color' => 'required',

        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $datas = Setting::first();
            if ($datas) {
                return $this->error("FAILED", "Setting sudah ada!", 400);
            }
            $banner = $request->hasFile('banner') ? 'banner_' . time() . "." . $request->banner->getClientOriginalExtension() : "";
            $banner_mobile = $request->hasFile('banner_mobile') ? 'banner_mobile_' . time() . "." . $request->banner_mobile->getClientOriginalExtension() : "";
            $banner_tablet = $request->hasFile('banner_tablet') ? 'banner_tablet_' . time() . "." . $request->banner_tablet->getClientOriginalExtension() : "";


            $data = [
                'color' => $request->color,
                'banner' => $banner,
                'banner_mobile' => $banner_mobile,
                'banner_tablet' => $banner_tablet,
                'created_by' => Auth::user()->id
            ];
            // Create Setting
            $add = Setting::create($data);

            if ($add) {

                // Save Image in Storage folder setting
                Helper::saveImage('banner', $banner, $request, $this->destinationImage);
                Helper::saveImage('banner_mobile', $banner_mobile, $request, $this->destinationImage);
                Helper::saveImage('banner_tablet', $banner_tablet, $request, $this->destinationImage);

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
        $validator = Validator::make($request->all(), [
            'banner'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
            'banner_mobile'   => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
            'banner_tablet'   => 'image|mimes:jpeg,png,jpg,gif,svg|max:5012',
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
            $banner = $request->hasFile('banner') ? 'banner_' . time() . "." . $request->banner->getClientOriginalExtension() : "";
            $banner_mobile = $request->hasFile('banner_mobile') ? 'banner_mobile_' . time() . "." . $request->banner_mobile->getClientOriginalExtension() : "";
            $banner_tablet = $request->hasFile('banner_tablet') ? 'banner_tablet_' . time() . "." . $request->banner_tablet->getClientOriginalExtension() : "";

            $datas['color'] = $request->filled('color') ? $request->color : $datas->color;

            $datas['edited_by'] = Auth::user()->id;

            if ($request->hasFile('banner')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner);
                // public storage
                $datas['banner'] = $banner;
                Helper::saveImage('banner', $banner, $request, $this->destinationImage);
            } else {
                if ($request->delete_banner) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner);

                    $datas['banner'] = null;
                }
                $datas['banner'] = $datas->banner;
            }

            if ($request->hasFile('banner_mobile')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner_mobile);
                // public storage
                $datas['banner_mobile'] = $banner_mobile;
                Helper::saveImage('banner_mobile', $banner_mobile, $request, $this->destinationImage);
            } else {
                if ($request->delete_banner_mobile) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner_mobile);

                    $datas['banner_mobile'] = null;
                }
                $datas['banner_mobile'] = $datas->banner_mobile;
            }

            if ($request->hasFile('banner_tablet')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner_tablet);
                // public storage
                $datas['banner_tablet'] = $banner_tablet;
                Helper::saveImage('banner_tablet', $banner_tablet, $request, $this->destinationImage);
            } else {
                if ($request->delete_banner_tablet) {
                    // Old image delete
                    Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->banner_tablet);

                    $datas['banner_tablet'] = null;
                }
                $datas['banner_tablet'] = $datas->banner_tablet;
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
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->banner);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->banner_mobile);
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->banner_tablet);


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
