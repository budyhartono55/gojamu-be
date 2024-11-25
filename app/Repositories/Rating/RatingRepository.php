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


    public function getRatings($request)
    {
        $limit = Helper::limitDatas($request);
        $getId = $request->id;
        // $getType = $request->type;
        $getCtg = $request->ctg;


        // if (!empty($getType)) {
        //     return $getType == 'photo' || $getType == 'video'
        //         ? self::getAllMedia($getType, $limit, $getCtg)
        //         : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        // USED
        // if (!empty($getType)) {
        //     return in_array($getType, ['photo', 'video', 'streaming'])
        //         ? self::getAllMedia($getType, $limit, $getCtg)
        //         : $this->error("Not Found", "Type ($getType) tidak terdaftar", 404);
        // } else

        if (!empty($getId)) {
            return self::findById($getId);
        } else {
            return self::getAllRatings($getCtg, $limit);
        }
    }

    public function getAllRatings($slug, $limit)
    {
        try {
            $page = request()->get("page", 1);
            $key = $this->generalRedisKeys . "public_All_" . $page . '_limit#' . $limit . ($slug ? '_slug#' . $slug : '');
            $keyAuth = $this->generalRedisKeys . "auth_All_" . $page . '_limit#' . $limit . ($slug ? '_slug#' . $slug : '');
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Rating", $result);
            }

            $ratingQuery = Rating::with(['ctg_galleries', 'createdBy', 'editedBy'])
                ->latest('created_at');

            if ($slug) {
                $ratingQuery->whereHas('ctg_galleries', function ($query) use ($slug) {
                    $query->where('slug', $slug);
                });
            }
            $rating = $ratingQuery->paginate($limit);

            if ($rating) {
                $modifiedData = $rating->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(["id", "name"]);
                    $item->edited_by = optional($item->editedBy)->only(["id", "name"]);
                    $item->ctg_rating_id = optional($item->ctg_galleries)->only(['id', "title_ctg", "slug"]);

                    unset($item->createdBy, $item->editedBy, $item->ctg_galleries);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($rating));

                return $this->success("List Keseluruhan Rating", $modifiedData);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "Auth_";
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Rating dengan ID = ($id)", $result);
            }

            $rating = Rating::find($id);
            if ($rating) {
                $createdBy = User::select('id', 'name')->find($rating->created_by);
                $editedBy = User::select('id', 'name')->find($rating->edited_by);
                $rating->created_by = optional($createdBy)->only(['id', 'name']);
                $rating->edited_by = optional($editedBy)->only(['id', 'name']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($rating));

                return $this->success("Rating dengan ID $id", $rating);
            } else {
                return $this->error("Not Found", "Rating dengan ID $id tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
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

            //syncMedia
            // $averageRating = Rating::where('media_id', $mediaId)->avg('rating');
            // // $ratingCount = Rating::where('media_id', $mediaId)->count();
            // $media->update([
            //     'rate_total' => $averageRating,
            //     // 'rate_count' => $ratingCount,
            // ]);

            return $this->success("Sync rate ke media berhasil direkam!", $media);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}
