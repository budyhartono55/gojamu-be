<?php

namespace App\Repositories\Media;

use App\Repositories\Media\MediaInterface as MediaInterface;
use App\Models\Media;
use App\Models\User;
use App\Http\Resources\MediaResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\MediaRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\CtgMedia;
use App\Models\Topic;
use App\Models\Like;
use App\Models\Report;
use App\Models\Rating;
use App\Models\Comment;
use App\Models\Favorite_Media;
use Carbon\Carbon;
use App\Models\Wilayah\Kecamatan;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;

class MediaRepository implements MediaInterface
{

    protected $media;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Media $media)
    {
        $this->media = $media;
        $this->generalRedisKeys = "media_";
    }

    // getAll
    public function getMedias($request)
    {
        $limit = Helper::limitDatas($request);
        $getCategory = $request->ctg;
        $getKeyword =  $request->search;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllMediaByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                return self::getAllMediaByCategorySlug($getCategory, $limit);
            }
        } elseif (!empty($getKeyword)) {
            return self::getAllMediaByKeyword($getKeyword, $limit);
        } else {
            return self::getAllMedias($limit);
        }
    }

    public function getAllMedias($limit)
    {
        try {

            $key = $this->generalRedisKeys . "public_All_" . "limit_" . $limit . "_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . "limit_" . $limit . "_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Konten/Media", $result);
            }

            $userId = Auth::id();
            $media = Media::with([
                'createdBy',
                'editedBy',
                'ctgMedias',
                'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }
            ])
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at')
                ->paginate($limit);
            //clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($media));
                return $this->success("List keseluruhan Konten/Media", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeywordInCtg($slug, $keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Konten/Media dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $category = CtgMedia::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }
            $userId = Auth::id();
            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where('ctg_media_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at')
                ->paginate($limit);

            //clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            // if ($media->total() > 0) {
            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $media);
            }
            return $this->error("Not Found", "Konten/Media dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllMediaByCategorySlug($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("(CACHE): List Keseluruhan Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug).", $result);
            }
            $userId = Auth::id();
            $category = CtgMedia::where('slug', $slug)->first();
            if ($category) {
                $media = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }])
                    ->where('ctg_media_id', $category->id)
                    ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    }])
                    ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    }])
                    ->latest('created_at')
                    ->paginate($limit);

                //clear eager load topics
                foreach ($media->items() as $mediaItem) {
                    foreach ($mediaItem->topics as $topic) {
                        $topic->makeHidden(['pivot']);
                    }
                }

                // if ($media->total() > 0) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media berdasarkan Kategori Konten/Media dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeyword($keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Konten/Media dengan keyword = ($keyword).", $result);
            }

            $userId = Auth::id();
            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at')
                ->paginate($limit);

            //clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media berdasarkan keyword = ($keyword)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media dengan keyword = ($keyword) tidak ditemukan!", 404);
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
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Konten/Media dengan ID = ($id)", $result);
            }
            $userId = Auth::id();

            // withCount untuk cek liked_stat
            $media = Media::withCount(['likes as liked_stat' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->find($id);

            if ($media) {
                $createdBy = User::select('id', 'name', 'image')->find($media->created_by);
                $editedBy = User::select('id', 'name')->find($media->edited_by);
                $ctgMedia = CtgMedia::select('id', 'title_ctg', 'slug')->find($media->ctg_media_id);
                $topics = $media->topics()->select('id', 'title', 'slug')->get();

                $media->created_by = optional($createdBy)->only(['id', 'name', 'image']);
                $media->edited_by = optional($editedBy)->only(['id', 'name']);
                $media->ctg_media_id = optional($ctgMedia)->only(['id', 'title_ctg', 'slug']);
                $media->topics = $topics->map(function ($topic) {
                    return $topic->only(['id', 'title', 'slug']);
                });

                $userId = Auth::id();
                if ($userId) {
                    $liked = Like::where('user_id', $userId)->where('media_id', $id)->exists();
                    $media->liked_stat = $liked ? 1 : 0;
                } else {
                    $media->liked_stat = 0;
                }

                $comments = $media->comments()
                    ->with([
                        'users:id,name,image',
                        'replies' => function ($query) {
                            $query->with('users:id,name,image')
                                ->take(1);
                        }
                    ])
                    ->whereNull('parent_id')
                    ->orderBy('created_at', 'desc')
                    ->get();


                $comments->each(function ($comment) {
                    $comment->user_name = $comment->users->name;
                    $comment->user_image = $comment->users->image;
                    unset($comment->users);
                });
                $media->comments = $comments;

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($media));
                return $this->success("Detail Konten/Media dengan ID = ($id)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createMedia($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_media' =>  'required',
                'ctg_media_id' =>  'required',
                'topic_id' =>  'required',
                'ytb_url' =>  'required',
            ],
            [
                'title_media.required' => 'Mohon masukkan nama media!',
                'ytb_url.required' => 'URL video tidak boleh Kosong!',
                'topic_id.required' => 'Masukkan topik media!',
                'topic_id.array' => 'Masukkan topik media berupa array!',
                'ctg_media_id.required' => 'Masukkan kategori media!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $media = new Media();
            $media->title_media = $request->title_media;
            $media->description = $request->description;
            $media->ytb_url = $request->ytb_url ?? '';
            $media->posted_at = Carbon::now();
            // $media->report_stat = 'normal'; //default

            //ctg_media_id
            $ctg_media_id = $request->ctg_media_id;
            $ctg = CtgMedia::where('id', $ctg_media_id)->first();
            if ($ctg) {
                $media->ctg_media_id = $ctg_media_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori Media dengan ID = ($ctg_media_id) tidak ditemukan!", 404);
            }

            //topics
            $cleaned_topic_ids = str_replace(' ', '', $request->topic_id);
            $topic_ids = explode(',', $cleaned_topic_ids);
            foreach ($topic_ids as $topic_id) {
                $topic = Topic::where('id', $topic_id)->first();
                if (!$topic) {
                    return $this->error("Tidak ditemukan!", "Topik dengan ID = ($topic_id) tidak ditemukan!", 404);
                }
            }

            $user = Auth::user();
            $media->user_id = $user->id;
            $media->created_by = $user->id;
            $media->edited_by = $user->id;

            // save
            $create = $media->save();
            $media->topics()->attach($topic_ids);

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Media Berhasil ditambahkan!", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function updateMedia($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_media' =>  'required',
                'ctg_media_id' =>  'required',
                'topic_id' =>  'required',
                'ytb_url' =>  'required',
            ],
            [
                'title_media.required' => 'Mohon masukkan nama media!',
                'ytb_url.required' => 'URL video tidak boleh Kosong!',
                'topic_id.required' => 'Masukkan topik media!',
                'ctg_media_id.required' => 'Masukkan ketegori media!',
            ]
        );
        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $media = Media::find($id);
            if (!$media) {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
            $user = Auth::user();
            if ($media->user_id !== $user->id && $user->role !== 'Admin') {
                return $this->error("Forbidden", "Anda tidak memiliki akses untuk melakukan update pada media ini!", 403);
            }

            // Approved updates
            $media['title_media'] = $request->title_media ?? $media->title_media;
            $media['description'] = $request->description ?? $media->description;
            $media['ytb_url'] = $request->ytb_url ?? $media->ytb_url;
            $media['report_stat'] = $request->report_stat ?? $media->report_stat;

            $ctg_media_id = $request->ctg_media_id;
            $ctg = CtgMedia::where('id', $ctg_media_id)->first();
            if ($ctg) {
                $media['ctg_media_id'] = $ctg_media_id ?? $media->ctg_media_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori media dengan ID = ($ctg_media_id) tidak ditemukan!", 404);
            }

            $cleaned_topic_ids = str_replace(' ', '', $request->topic_id);
            $topic_ids = explode(',', $cleaned_topic_ids);
            foreach ($topic_ids as $topic_id) {
                $topic = Topic::where('id', $topic_id)->first();
                if (!$topic) {
                    return $this->error("Tidak ditemukan!", "Topik dengan ID = ($topic_id) tidak ditemukan!", 404);
                }
            }

            $media['edited_by'] = Auth::id();

            // Save
            $update = $media->save();
            $media->topics()->sync($topic_ids);

            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Media Berhasil diperbaharui!", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }


    // delete
    public function deleteMedia($id)
    {
        try {
            DB::beginTransaction();
            // search
            $media = Media::find($id);
            if (!$media) {
                return $this->error("Not Found", "Konten/Media dengan ID = ($id) tidak ditemukan!", 404);
            }
            $user = Auth::user();
            if ($media->user_id !== $user->id && $user->role !== 'Admin') {
                return $this->error("Forbidden", "Anda tidak memiliki akses untuk melakukan delete pada media ini!", 403);
            }

            //sync
            Like::where('media_id', $id)->delete();
            Favorite_Media::where('media_id', $id)->delete();
            Comment::where('media_id', $id)->delete();
            Report::where('media_id', $id)->delete();
            // approved
            $media->topics()->detach();
            $del = $media->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                DB::commit();
                return $this->success("COMPLETED", "Konten/Media dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllMediasAttention()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_attention_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_attention_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Daftar media dengan status perhatian", $result);
            }

            $userId = Auth::id();
            $media = Media::with([
                'createdBy',
                'editedBy',
                'ctgMedias',
                'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }
            ])
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->where('report_stat', 'attention')
                ->latest('created_at')
                ->paginate(12);

            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                Redis::setex($key, 60, json_encode($media));
                return $this->success("Daftar media dengan status perhatian", $media);
            }

            return $this->success("Tidak ada media dengan status perhatian.", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    //META/OWNER=====================================================================================================
    public function getMediasOwner($request)
    {
        $limit = Helper::limitDatas($request);
        $getCategoryOwner = $request->meta_ctg;
        $getKeywordOwner =  $request->meta_search;

        if (!empty($getCategoryOwner)) {
            if (!empty($getKeywordOwner)) {
                return self::getAllMediaByKeywordInCtgOwner($getCategoryOwner, $getKeywordOwner, $limit);
            } else {
                return self::getAllMediaByCategorySlugOwner($getCategoryOwner, $limit);
            }
        } elseif (!empty($getKeywordOwner)) {
            return self::getAllMediaByKeywordOwner($getKeywordOwner, $limit);
        } else {
            return self::getAllMediasOwner($limit);
        }
    }
    public function getAllMediasOwner($limit)
    {
        try {
            $userId = Auth::id();
            $key = $this->generalRedisKeys . "user_" . $userId . "_public_All_" . "limit_" . $limit . "_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "user_" . $userId . "_auth_All_" . "limit_" . $limit . "_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Konten/Media Milik User", $result);
            }

            $user = Auth::user();
            $media = Media::with([
                'createdBy',
                'editedBy',
                'ctgMedias',
                'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }
            ])
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at');
            if ($user->role !== 'Admin') {
                $media->where('user_id', $user->id);
            }
            $media = $media->paginate($limit);

            // Clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($media));
                return $this->success("List Keseluruhan Konten/Media Milik User", $media);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeywordInCtgOwner($slug, $keyword, $limit)
    {
        try {
            $userId = Auth::id();
            $key = $this->generalRedisKeys . "user_" . $userId . "_public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "user_" . $userId . "_auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Konten/Media Milik User dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $user = Auth::user();
            $category = CtgMedia::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }
            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where('ctg_media_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at');
            if ($user->role !== 'Admin') {
                $media->where('user_id', $user->id);
            }
            $media = $media->paginate($limit);

            //clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            // if ($media->total() > 0) {
            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media Milik User berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $media);
            }
            return $this->error("Not Found", "Konten/Media Milik User dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
    public function getAllMediaByCategorySlugOwner($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $userId = Auth::id();

            $key = $this->generalRedisKeys . "user_" . $userId . "_public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "user_" . $userId . "_auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("(CACHE): List Media Milik User berdasarkan kategori slug = ($slug).", $result);
            }

            $user = Auth::user();
            $category = CtgMedia::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $query = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where('ctg_media_id', $category->id)
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at');

            if ($user && $user->role !== 'Admin') {
                $query->where('user_id', $userId);
            }
            $media = $query->paginate($limit);

            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            $modifiedData = $media->items();
            $modifiedData = array_map(function ($item) {
                $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                return $item;
            }, $modifiedData);

            $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
            Redis::setex($key, 60, json_encode($media));

            return $this->success("List Media Milik User berdasarkan kategori slug = ($slug).", $media);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllMediaByKeywordOwner($keyword, $limit)
    {
        try {
            $userId = Auth::id();
            $key = $this->generalRedisKeys . "user_" . $userId . "_public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "user_" . $userId . "_auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Konten/Media Milik User dengan keyword = ($keyword).", $result);
            }

            $user = Auth::user();
            $media = Media::with(['createdBy', 'editedBy', 'ctgMedias', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where(function ($query) use ($keyword) {
                    $query->where('title_media', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->withCount(['favorites_medias as favorited_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at');
            if ($user->role !== 'Admin') {
                $media->where('user_id', $user->id);
            }
            $media = $media->paginate($limit);

            //clear eager load topics
            foreach ($media->items() as $mediaItem) {
                foreach ($mediaItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($media) {
                $modifiedData = $media->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_media_id = optional($item->ctgMedias)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgMedias);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($media));

                return $this->success("List Keseluruhan Konten/Media Milik User berdasarkan keyword = ($keyword)", $media);
            } else {
                return $this->error("Not Found", "Konten/Media Milik User dengan keyword = ($keyword) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
