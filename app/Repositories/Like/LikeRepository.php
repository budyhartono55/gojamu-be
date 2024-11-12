<?php

namespace App\Repositories\Like;

use App\Repositories\Like\LikeInterface as LikeInterface;
use App\Models\Like;
use App\Models\Media;
use App\Traits\API_response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;



class LikeRepository implements LikeInterface
{
    protected $like;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Like $like)
    {
        $this->like = $like;
    }

    // create
    public function toggleLike($request)
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
                //checkLike
                $existingLike = Like::where('user_id', $user->id)->where('media_id', $mediaId)->first();

                if ($existingLike) {
                    $existingLike->delete();
                    Media::where('id', $mediaId)->decrement('like_count');
                    return $this->success("Unlike", "Berhasil melakukan Unlike!");
                } else {
                    $newLike = new Like();
                    $newLike->user_id = $user->id;
                    $newLike->media_id = $mediaId;
                    $newLike->posted_at = Carbon::now();
                    $newLike->created_by = $user->id;
                    $newLike->edited_by = $user->id;

                    $newLike->save();
                    Media::where('id', $mediaId)->increment('like_count');
                    return $this->success("Like berhasil direkam!", $newLike);
                }
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 500);
        }
    }
}
