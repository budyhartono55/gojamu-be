<?php

namespace App\Repositories\Favorite_Media;

use App\Repositories\Favorite_Media\Favorite_MediaInterface as Favorite_MediaInterface;
use App\Models\Favorite_Media;
use App\Models\Media;
use App\Traits\API_response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;



class Favorite_MediaRepository implements Favorite_MediaInterface
{
    protected $favorite_media;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Favorite_Media $favorite_media)
    {
        $this->favorite_media = $favorite_media;
        $this->generalRedisKeys = "media_";
    }

    // create
    public function toggleFavorite_Media($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'media_id' =>  'required',
            ],
            [
                'media_id.required' => 'id Media tidak boleh Kosong!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $user = Auth::user();
            $mediaId = $request->media_id;
            $checkMed = Media::find($mediaId);
            if (!$checkMed) {
                return $this->error("Not Found", "Media dengan ID = ($mediaId) tidak ditemukan!", 404);
            } else {
                //checkFavorite_Media
                $existingFavorite_Media = Favorite_Media::where('user_id', $user->id)->where('media_id', $mediaId)->first();

                if ($existingFavorite_Media) {

                    $exist = $existingFavorite_Media->delete();
                    if ($exist) {
                        // Media::where('id', $mediaId)->decrement('favorite_media_count');
                        RedisHelper::dropKeys($this->generalRedisKeys);
                        return $this->success("Unfavorite_media", "Berhasil melakukan Unfavorite Media!");
                    }
                } else {
                    $newFavorite_Media = new Favorite_Media();
                    $newFavorite_Media->user_id = $user->id;
                    $newFavorite_Media->media_id = $mediaId;
                    $newFavorite_Media->posted_at = Carbon::now();
                    $newFavorite_Media->created_by = $user->id;
                    $newFavorite_Media->edited_by = $user->id;

                    $create = $newFavorite_Media->save();
                    if ($create) {
                        // Media::where('id', $mediaId)->increment('favorite_media_count');
                        RedisHelper::dropKeys($this->generalRedisKeys);
                        return $this->success("Media berhasil direkam sebagai Favorite!", $newFavorite_Media);
                    }
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }
}