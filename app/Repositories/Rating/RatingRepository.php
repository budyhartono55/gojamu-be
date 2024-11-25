<?php

namespace App\Repositories\Rating;

use App\Repositories\Rating\RatingInterface as RatingInterface;
use App\Models\Rating;
use App\Models\User;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use Carbon\Carbon;

class RatingRepository implements RatingInterface
{

    protected $rating;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Rating $rating)
    {
        $this->rating = $rating;
        $this->generalRedisKeys = "rating_";
    }

    public function getRatingsByMediaId($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Daftar rating untuk Media dengan ID = ($id)", $result);
            }
            $ratings = Rating::where('media_id', $id)
                ->with('users:id,name,image')
                ->get(['rating', 'description', 'user_id', 'created_at']);
            if ($ratings->isEmpty()) {
                return $this->error("Tidak ditemukan", "Tidak ada rating untuk media dengan ID = ($id)", 404);
            }

            $key = Auth::check() ? $keyAuth . $id : $key . $id;
            Redis::setex($key, 60, json_encode($ratings));
            return $this->success("Daftar Rating untuk Media dengan ID = ($id)", $ratings);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function rate($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'media_id' => 'required',
                'rating' => 'required|integer|min:1|max:5',
            ],
            [
                'media_id.required' => 'Media tidak boleh kosong!',
                'rating.required' => 'Rating tidak boleh kosong!',
                'rating.integer' => 'Rating harus berupa angka!',
                'rating.min' => 'Rating minimal adalah 1!',
                'rating.max' => 'Rating maksimal adalah 5!',
            ]
        );
        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Upps, Validasi Gagal!", $validator->errors(), 400);
        }

        try {
            $mediaId = $request->media_id;
            $userId = Auth::id();
            $media = Media::findOrFail($mediaId);

            $existingRating = Rating::where('user_id', $userId)
                ->where('media_id', $mediaId)
                ->first();

            if ($existingRating) {
                $existingRating->update([
                    'rating' => $request->rating,
                    'description' => $request->description,
                    'edited_by' => $userId,
                ]);
                $averageRating = Rating::where('media_id', $mediaId)->avg('rating');
                $media->update(['rate_total' => $averageRating]);

                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Rating Berhasil diupdate!", $existingRating);
            } else {
                $rating = new Rating();
                $rating->rating = $request->rating;
                $rating->media_id = $mediaId;
                $rating->description = $request->description;
                $rating->posted_at = Carbon::now();

                $rating->user_id = $userId;
                $rating->created_by = $userId;
                $rating->edited_by = $userId;

                $create = $rating->save();
                if ($create) {
                    $averageRating = Rating::where('media_id', $mediaId)->avg('rating');
                    $media->update(['rate_total' => $averageRating]);

                    Media::where('id', $mediaId)->increment('rate_count');
                    RedisHelper::dropKeys($this->generalRedisKeys);
                    return $this->success("Rating Berhasil ditambahkan!", $rating);
                }
            }

            return $this->success("Sync rate ke media berhasil direkam!", $media);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}