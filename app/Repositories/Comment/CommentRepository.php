<?php

namespace App\Repositories\Comment;

use App\Repositories\Comment\CommentInterface as CommentInterface;
use App\Models\Comment;
use App\Models\User;
use App\Http\Resources\CommentResource;
use Exception;
use Illuminate\Http\Request;
use App\Traits\API_response;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CommentRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;
use App\Models\CtgComment;
use App\Models\Topic;
use Carbon\Carbon;
use App\Models\Wilayah\Kecamatan;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Facades\Image;

class CommentRepository implements CommentInterface
{

    protected $comment;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
        $this->generalRedisKeys = "comment_";
    }

    // getAll
    public function getComments($request)
    {
        $limit = Helper::limitDatas($request);
        // $getSlug = $request->slug;
        $getCategory = $request->ctg;
        $getKeyword =  $request->search;

        if (!empty($getCategory)) {
            if (!empty($getKeyword)) {
                return self::getAllCommentByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                return self::getAllCommentByCategorySlug($getCategory, $limit);
            }
            // } elseif (!empty($getSlug)) {
            //     return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            return self::getAllCommentByKeyword($getKeyword, $limit);
        } else {
            return self::getAllComments();
        }
    }

    public function getAllComments()
    {
        try {

            $key = $this->generalRedisKeys . "public_All_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): List Keseluruhan Konten/Comment", $result);
            }

            $userId = Auth::id();
            // $comment = Comment::with(['createdBy', 'editedBy', 'ctgComments', 'topics' => function ($query) {
            //     $query->select('id', 'title', 'slug');
            // }])
            //     ->latest('created_at')
            //     ->paginate(12);
            $comment = Comment::with([
                'createdBy',
                'editedBy',
                'ctgComments',
                'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }
            ])
                ->withCount(['likes as liked_stat' => function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }])
                ->latest('created_at')
                ->paginate(12);
            //clear eager load topics
            foreach ($comment->items() as $commentItem) {
                foreach ($commentItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($comment) {
                $modifiedData = $comment->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['id', 'name']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);
                    $item->ctg_comment_id = optional($item->ctgComments)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgComments);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth : $key;
                Redis::setex($key, 60, json_encode($comment));
                return $this->success("List keseluruhan Konten/Comment", $comment);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllCommentByKeywordInCtg($slug, $keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $slug . "_" .  $keyword)) {
                $result = json_decode(Redis::get($key . $slug . "_" .  $keyword));
                return $this->success("(CACHE): List Konten/Comment dengan keyword = ($keyword) dalam Kategori ($slug).", $result);
            }

            $category = CtgComment::where('slug', $slug)->first();
            if (!$category) {
                return $this->error("Not Found", "Kategori dengan slug = ($slug) tidak ditemukan!", 404);
            }

            $comment = Comment::with(['createdBy', 'editedBy', 'ctgComments', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])
                ->where('ctg_comment_id', $category->id)
                ->where(function ($query) use ($keyword) {
                    $query->where('title_comment', 'LIKE', '%' . $keyword . '%')
                        ->orWhere('description', 'LIKE', '%' . $keyword . '%');
                })
                ->latest('created_at')
                ->paginate($limit);

            //clear eager load topics
            foreach ($comment->items() as $commentItem) {
                foreach ($commentItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            // if ($comment->total() > 0) {
            if ($comment) {
                $modifiedData = $comment->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_comment_id = optional($item->ctgComments)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgComments);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth .  $slug . "_" .  $keyword : $key .  $slug . "_" .  $keyword;
                Redis::setex($key, 60, json_encode($comment));

                return $this->success("List Keseluruhan Konten/Comment berdasarkan keyword = ($keyword) dalam Kategori ($slug)", $comment);
            }
            return $this->error("Not Found", "Konten/Comment dengan keyword = ($keyword) dalam Kategori ($slug)tidak ditemukan!", 404);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    public function getAllCommentByCategorySlug($slug, $limit)
    {
        try {
            $isAuthenticated = Auth::check();
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = $isAuthenticated ? $keyAuth : $key;

            if (Redis::exists($key . $slug)) {
                $result = json_decode(Redis::get($key . $slug));
                return $this->success("(CACHE): List Keseluruhan Konten/Comment berdasarkan Kategori Konten/Comment dengan slug = ($slug).", $result);
            }
            $category = CtgComment::where('slug', $slug)->first();
            if ($category) {
                $comment = Comment::with(['createdBy', 'editedBy', 'ctgComments', 'topics' => function ($query) {
                    $query->select('id', 'title', 'slug');
                }])
                    ->where('ctg_comment_id', $category->id)
                    ->latest('created_at')
                    ->paginate($limit);

                //clear eager load topics
                foreach ($comment->items() as $commentItem) {
                    foreach ($commentItem->topics as $topic) {
                        $topic->makeHidden(['pivot']);
                    }
                }

                // if ($comment->total() > 0) {
                $modifiedData = $comment->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_comment_id = optional($item->ctgComments)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgComments);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $slug : $key . $slug;
                Redis::setex($key, 60, json_encode($comment));

                return $this->success("List Keseluruhan Konten/Comment berdasarkan Kategori Konten/Comment dengan slug = ($slug)", $comment);
            } else {
                return $this->error("Not Found", "Konten/Comment berdasarkan Kategori Konten/Comment dengan slug = ($slug) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    public function getAllCommentByKeyword($keyword, $limit)
    {
        try {
            $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
            $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
            $key = Auth::check() ? $keyAuth : $key;
            if (Redis::exists($key . $keyword)) {
                $result = json_decode(Redis::get($key . $keyword));
                return $this->success("(CACHE): List Konten/Comment dengan keyword = ($keyword).", $result);
            }

            $comment = Comment::with(['createdBy', 'editedBy', 'ctgComments', 'topics' => function ($query) {
                $query->select('id', 'title', 'slug');
            }])->where(function ($query) use ($keyword) {
                $query->where('title_comment', 'LIKE', '%' . $keyword . '%')
                    ->orWhere('description', 'LIKE', '%' . $keyword . '%');
            })
                ->latest('created_at')
                ->paginate($limit);

            //clear eager load topics
            foreach ($comment->items() as $commentItem) {
                foreach ($commentItem->topics as $topic) {
                    $topic->makeHidden(['pivot']);
                }
            }

            if ($comment) {
                $modifiedData = $comment->items();
                $modifiedData = array_map(function ($item) {

                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    $item->ctg_comment_id = optional($item->ctgComments)->only(['id', 'title_ctg', 'slug']);

                    unset($item->createdBy, $item->editedBy, $item->ctgComments);
                    return $item;
                }, $modifiedData);

                $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
                Redis::setex($key, 60, json_encode($comment));

                return $this->success("List Keseluruhan Konten/Comment berdasarkan keyword = ($keyword)", $comment);
            } else {
                return $this->error("Not Found", "Konten/Comment dengan keyword = ($keyword) tidak ditemukan!", 404);
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
                return $this->success("(CACHE): Detail Konten/Comment dengan ID = ($id)", $result);
            }

            $comment = Comment::find($id);
            if ($comment) {
                $createdBy = User::select('name')->find($comment->created_by);
                $editedBy = User::select('name')->find($comment->edited_by);
                $ctgComment = CtgComment::select('id', 'title_ctg', 'slug')->find($comment->ctg_comment_id);
                $topics = $comment->topics()->select('id', 'title', 'slug')->get();

                $comment->created_by = optional($createdBy)->only(['name']);
                $comment->edited_by = optional($editedBy)->only(['name']);
                $comment->ctg_comment_id = optional($ctgComment)->only(['id', 'title_ctg', 'slug']);
                $comment->topics = $topics->map(function ($topic) {
                    return $topic->only(['id', 'title', 'slug']);
                });

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($comment));
                return $this->success("Detail Konten/Comment dengan ID = ($id)", $comment);
            } else {
                return $this->error("Not Found", "Konten/Comment dengan ID = ($id) tidak ditemukan!", 404);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // create
    public function createComment($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_comment' =>  'required',
                'ctg_comment_id' =>  'required',
                'topic_id' =>  'required',
                'ytb_url' =>  'required',
            ],
            [
                'title_comment.required' => 'Mohon masukkan nama konten/comment!',
                'ytb_url.required' => 'URL video tidak boleh Kosong!',
                'topic_id.required' => 'Masukkan topik konten/comment!',
                'topic_id.array' => 'Masukkan topik konten/comment berupa array!',
                'ctg_comment_id.required' => 'Masukkan ketegori konten/comment!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $comment = new Comment();
            $comment->title_comment = $request->title_comment;
            $comment->description = $request->description;
            $comment->ytb_url = $request->ytb_url ?? '';
            $comment->posted_at = Carbon::now();
            $comment->report_stat = 'Normal'; //default

            //ctg_comment_id
            $ctg_comment_id = $request->ctg_comment_id;
            $ctg = CtgComment::where('id', $ctg_comment_id)->first();
            if ($ctg) {
                $comment->ctg_comment_id = $ctg_comment_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori Comment dengan ID = ($ctg_comment_id) tidak ditemukan!", 404);
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
            $comment->user_id = $user->id;
            $comment->created_by = $user->id;
            $comment->edited_by = $user->id;

            // save
            $create = $comment->save();
            $comment->topics()->attach($topic_ids);

            if ($create) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Comment Berhasil ditambahkan!", $comment);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    // update
    public function updateComment($request, $id)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title_comment' =>  'required',
                'ctg_comment_id' =>  'required',
                'topic_id' =>  'required',
                'ytb_url' =>  'required',
            ],
            [
                'title_comment.required' => 'Mohon masukkan nama konten/comment!',
                'ytb_url.required' => 'URL video tidak boleh Kosong!',
                'topic_id.required' => 'Masukkan topik konten/comment!',
                'ctg_comment_id.required' => 'Masukkan ketegori konten/comment!',
            ]
        );

        //check if validation fails
        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }
        try {
            // search
            $comment = Comment::find($id);

            // checkID
            if (!$comment) {
                return $this->error("Not Found", "Konten/Comment dengan ID = ($id) tidak ditemukan!", 404);
            }


            // approved
            $comment['title_comment'] = $request->title_comment ?? $comment->title_comment;
            $comment['description'] = $request->description ?? $comment->description;
            $comment['ytb_url'] = $request->ytb_url ?? $comment->ytb_url;
            $comment['report_stat'] = $request->report_stat ?? $comment->report_stat;

            $ctg_comment_id = $request->ctg_comment_id;
            $ctg = CtgComment::where('id', $ctg_comment_id)->first();
            if ($ctg) {
                $comment['ctg_comment_id'] = $ctg_comment_id ?? $comment->ctg_comment_id;
            } else {
                return $this->error("Tidak ditemukan!", "Kategori comment dengan ID = ($ctg_comment_id) tidak ditemukan!", 404);
            }

            $cleaned_topic_ids = str_replace(' ', '', $request->topic_id);
            $topic_ids = explode(',', $cleaned_topic_ids);
            foreach ($topic_ids as $topic_id) {
                $topic = Topic::where('id', $topic_id)->first();
                if (!$topic) {
                    return $this->error("Tidak ditemukan!", "Topik dengan ID = ($topic_id) tidak ditemukan!", 404);
                }
            }

            $comment['user_id'] = $comment->user_id;
            $comment['created_by'] = $comment->created_by;
            $comment['edited_by'] = Auth::user()->id;

            //save
            $update = $comment->save();
            $comment->topics()->sync($topic_ids);

            if ($update) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Konten/Comment Berhasil diperbaharui!", $comment);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteComment($id)
    {
        try {
            // search
            $comment = Comment::find($id);
            if (!$comment) {
                return $this->error("Not Found", "Konten/Comment dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $comment->topics()->detach();
            $del = $comment->delete();
            if ($del) {
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Konten/Comment dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}