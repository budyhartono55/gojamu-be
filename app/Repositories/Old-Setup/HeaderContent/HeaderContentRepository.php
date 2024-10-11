<?php

namespace App\Repositories\HeaderContent;

use App\Helpers\Helper;
use App\Models\HeaderContent;
use App\Repositories\HeaderContent\HeaderContentInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class HeaderContentRepository implements HeaderContentInterface
{
    private $headerContent;
    // 1 hour redis expired
    private $expired = 3600;
    private $keyRedis = 'headerContent-';
    private $destinationImage = "images";
    private $destinationImageThumbnail = "thumbnails/t_images";
    use API_response;

    public function __construct(HeaderContent $headerContent)
    {
        $this->headerContent = $headerContent;
    }


    public function getAll($request)
    {
        try {
            $limit = Helper::limitDatas($request);
            $nameLogin = !Auth::check() ? "-public-" : "-admin-";
            $keyOne = $this->keyRedis . "getAll" . $nameLogin . request()->get('page', 1)  . "#limit" . $limit;
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("List Data HeaderContent from (CACHE)", $result);
            }
            $datas = HeaderContent::latest('created_at')->paginate($limit);
            $data = Helper::queryModifyUserForDatas($datas, true);
            if (!Auth::check() and $datas) {
                $hidden = ['id'];
                $data->makeHidden($hidden);
            }
            Redis::set($keyOne, json_encode($data));
            Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
            return $this->success("List Data HeaderContent", $data);

            // $data = HeaderContent::latest('created_at')->paginate(10);

            // return $this->success(
            //     " List semua data HeaderContent",
            //     $data
            // );
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
    // findOne
    public function getById($id)
    {
        try {
            $nameLogin = !Auth::check() ? "-public-" : "-admin-";
            $keyOne = $this->keyRedis . "getById-" . $nameLogin . Str::slug($id);
            if (Redis::exists($keyOne)) {
                $result = json_decode(Redis::get($keyOne));
                return $this->success("HeaderContent dengan ID = ($id) from (CACHE)", $result);
            }

            $datas = HeaderContent::find($id);
            if (!empty($datas)) {
                $data = Helper::queryModifyUserForDatas($datas);
                if (!Auth::check()) {
                    $hidden = ['id'];
                    $data->makeHidden($hidden);
                }
                Redis::set($keyOne, json_encode($data));
                Redis::expire($keyOne, $this->expired); // Cache for 60 seconds
                return $this->success("HeaderContent Dengan ID = ($id)", $data);
            }
            return $this->error("Not Found", "HeaderContent dengan ID = ($id) tidak ditemukan!", 404);

            // $data = HeaderContent::find($id);

            // // Check the data
            // if (!$data) return $this->error("HeaderContent dengan ID = ($id) tidak ditemukan!", 404);

            // return $this->success("Detail HeaderContent", $data);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function save($request)
    {
        $validator = Validator::make($request->all(), [
            'title_header' => 'required',
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',


        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }

        try {
            $image = $request->hasFile('image') ? 'header_content_' . time() . "." . $request->image->getClientOriginalExtension() : "";

            $data = [
                'title_header' => $request->title_header,
                'caption' => $request->caption,
                'image' => $image,
                'created_by' => Auth::user()->id
            ];
            // Create HeaderContent
            $add = HeaderContent::create($data);

            if ($add) {

                // Save Image in Storage folder headerContent
                Helper::saveImage('image', $image, $request, $this->destinationImage);

                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("HeaderContent Berhasil ditambahkan!", $data);
            }
            return $this->error("FAILED", "HeaderContent gagal ditambahkan!", 400);
        } catch (\Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request->all(), [
            'image'           => 'image|mimes:jpeg,png,jpg,gif,svg|max:3072',
        ]);
        if ($validator->fails()) {
            return $this->error("Upps, Validation Failed!", $validator->errors(), 422);
        }
        try {
            // search
            $datas = HeaderContent::find($id);
            // check
            if (!$datas) {
                return $this->error("Not Found", "HeaderContent dengan ID = ($id) tidak ditemukan!", 404);
            }
            $image = $request->hasFile('image') ? 'header_content_' . time() . "." . $request->image->getClientOriginalExtension() : "";

            $datas['title_header'] = $request->title_header;
            $datas['caption'] = $request->caption;
            $datas['edited_by'] = Auth::user()->id;


            if ($request->hasFile('image')) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $datas->image);
                // public storage
                $datas['image'] = $image;
                Helper::saveImage('image', $image, $request, $this->destinationImage);
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
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("HeaderContent Berhasil diperbaharui!", $datas);
            }
            return $this->error("FAILED", "HeaderContent Gagal diperbaharui!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            // search
            $data = HeaderContent::find($id);
            if (!$data) {
                return $this->error("Not Found", "HeaderContent dengan ID = ($id) tidak ditemukan!", 404);
            }


            // approved
            if ($data->delete()) {
                Helper::deleteImage($this->destinationImage, $this->destinationImageThumbnail, $data->image);
                Helper::deleteRedis($this->keyRedis . "*");
                return $this->success("COMPLETED", "HeaderContent dengan ID = ($id) Berhasil dihapus!");
            }
            return $this->error("FAILED", "HeaderContent dengan ID = ($id) Gagal dihapus!", 400);
        } catch (Exception $e) {
            // return $this->error($e->getMessage(), $e->getCode());
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
