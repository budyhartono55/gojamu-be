<?php

namespace  App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class Helper
{

    public static function deleteRedis($keyword)
    {
        if (Redis::keys("*Dashboard-countData")) {
            Redis::del("Dashboard-countData");
        }
        $check = Redis::keys("*" . $keyword);
        if ($check) {
            foreach ($check as $key) {
                $keyRedis = str_replace(env('REDIS_KEY'), "", $key);
                Redis::del($keyRedis);
            }
        }
    }

    public static function resizeImage($image, $fileName, $request)
    {
        $destination = 'public/thumbnails/t_images';
        self::resizeImageProses($image, $fileName, $request, $destination);
    }
    public static function resizeIcon($icon, $fileName, $request)
    {
        $destination = 'public/thumbnails/t_icons';
        self::resizeImageProses($icon, $fileName, $request, $destination);
    }
    private static function resizeImageProses($image, $fileName, $request, $destination)
    {
        if ($image->getSize() > 100 * 100) {
            $compressedImage = Image::make($image);
            $originalWidth = $compressedImage->width();
            $originalHeight = $compressedImage->height();
            $imageFormat = $compressedImage->mime();

            $quality = $request->input('quality', 50);
            while ($compressedImage->filesize() > 100 * 100 && $quality >= 10) {
                $compressedImage->resize($originalWidth * 0.5, $originalHeight * 0.5);

                $compressedImage->encode($imageFormat, $quality);
                $quality -= 5;
            }

            Storage::put($destination . '/' . $fileName, $compressedImage->stream());
        } else {
            $image->storeAs($destination, $fileName);
        }
    }

    public static function saveImage($fieldName, $fileName, $request, $destinationImage)
    {
        if ($request->hasFile($fieldName)) {
            $image = $request->file($fieldName);
            $image->storeAs($destinationImage, $fileName, ['disk' => 'public']);
            self::resizeImage($image, $fileName, $request);
        }
    }

    public static function saveFile($fieldName, $fileName, $request, $destinationFile)
    {
        if ($request->hasFile($fieldName)) {
            $files = $request->file($fieldName);
            $files->storeAs($destinationFile, $fileName, ['disk' => 'public']);
        }
    }

    public static function deleteImage($destinationImage, $destinationImageThumbnail, $fileName)
    {

        $storage = Storage::disk('public');
        if ($storage->exists($destinationImage . "/" . $fileName)) {
            $storage->delete($destinationImage . "/" . $fileName);
        }
        if ($storage->exists($destinationImageThumbnail . "/" . $fileName)) {
            $storage->delete($destinationImageThumbnail . "/" . $fileName);
        }
    }

    public static function deleteFile($destinationFile, $fileName)
    {

        $storage = Storage::disk('public');
        if ($storage->exists($destinationFile . "/" . $fileName)) {
            $storage->delete($destinationFile . "/" . $fileName);
        }
    }

    public static function queryGetUser($id)
    {
        $user = User::select('name')->where('id', $id)->first();
        return $user ? $user->name : null;
    }

    public static function queryGetUserModify($item)
    {
        $created_by = [
            'name' => self::queryGetUser($item['created_by']),
        ];
        $item->created_by = $created_by;

        $edited_by = [
            'name' => self::queryGetUser($item['edited_by']),
        ];
        $item->edited_by = $edited_by;
        return $item;
    }

    public static function queryModifyUserForDatas($datas, $manyResult = false, $paginate = true)
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

    public static function generateCode($length, $db, $field)
    {
        $kode = Str::random($length);

        while ($db::where($field, $kode)->exists()) {
            $kode = Str::random($length);
        }

        return $kode;
    }
    public static function getWithoutAuthAPI($url)
    {
        return Http::accept('application/json')->get($url);
    }

    public static function limitDatas($request, $maksimal = 20)
    {
        if (($request->limit != null) or ($request->limit != "")) {
            $limit = $request->limit > $maksimal ? $maksimal : $request->limit;
        } else {
            $limit = $maksimal;
        }
        return $limit;
    }

    public static function convertImageToBase64($path, $fileName)
    {
        $imagePath = storage_path("app/public/" . $path . $fileName);
        $image = "data:image/png;base64," . base64_encode(file_get_contents($imagePath));
        return $image;
    }

    // public static function isAdmin()
    // {
    //     return auth()->user()->level == "Admin" ? true : false;
    // }
    // public static function isUser()
    // {
    //     return auth()->user()->level == "User" ? true : false;
    // }
}
