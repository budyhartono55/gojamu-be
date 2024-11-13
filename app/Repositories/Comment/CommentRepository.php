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
use App\Models\Media;
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
                // return self::getAllCommentByKeywordInCtg($getCategory, $getKeyword, $limit);
            } else {
                // return self::getAllCommentByCategorySlug($getCategory, $limit);
            }
            // } elseif (!empty($getSlug)) {
            //     return self::showBySlug($getSlug);
        } elseif (!empty($getKeyword)) {
            // return self::getAllCommentByKeyword($getKeyword, $limit);
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
                return $this->success("(CACHE): List Keseluruhan Komentar", $result);
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
            ])
                ->latest('created_at')
                ->paginate(12);

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
                return $this->success("List keseluruhan Komentar", $comment);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // public function getAllCommentByKeyword($keyword, $limit)
    // {
    //     try {
    //         $key = $this->generalRedisKeys . "public_" . '_limit#' . $limit;
    //         $keyAuth = $this->generalRedisKeys . "auth_" . '_limit#' . $limit;
    //         $key = Auth::check() ? $keyAuth : $key;
    //         if (Redis::exists($key . $keyword)) {
    //             $result = json_decode(Redis::get($key . $keyword));
    //             return $this->success("(CACHE): List Komentar dengan keyword = ($keyword).", $result);
    //         }

    //         $comment = Comment::with(['createdBy', 'editedBy', 'ctgComments', 'topics' => function ($query) {
    //             $query->select('id', 'title', 'slug');
    //         }])->where(function ($query) use ($keyword) {
    //             $query->where('title_comment', 'LIKE', '%' . $keyword . '%')
    //                 ->orWhere('description', 'LIKE', '%' . $keyword . '%');
    //         })
    //             ->latest('created_at')
    //             ->paginate($limit);

    //         //clear eager load topics
    //         foreach ($comment->items() as $commentItem) {
    //             foreach ($commentItem->topics as $topic) {
    //                 $topic->makeHidden(['pivot']);
    //             }
    //         }

    //         if ($comment) {
    //             $modifiedData = $comment->items();
    //             $modifiedData = array_map(function ($item) {

    //                 $item->created_by = optional($item->createdBy)->only(['name']);
    //                 $item->edited_by = optional($item->editedBy)->only(['name']);
    //                 $item->ctg_comment_id = optional($item->ctgComments)->only(['id', 'title_ctg', 'slug']);

    //                 unset($item->createdBy, $item->editedBy, $item->ctgComments);
    //                 return $item;
    //             }, $modifiedData);

    //             $key = Auth::check() ? $keyAuth . $keyword : $key . $keyword;
    //             Redis::setex($key, 60, json_encode($comment));

    //             return $this->success("List Keseluruhan Komentar berdasarkan keyword = ($keyword)", $comment);
    //         } else {
    //             return $this->error("Not Found", "Komentar dengan keyword = ($keyword) tidak ditemukan!", 404);
    //         }
    //     } catch (\Exception $e) {
    //         return $this->error("Internal Server Error", $e->getMessage());
    //     }
    // }

    // findOne
    public function findById($id)
    {
        try {
            $key = $this->generalRedisKeys . "public_";
            $keyAuth = $this->generalRedisKeys . "auth_";
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key . $id)) {
                $result = json_decode(Redis::get($key . $id));
                return $this->success("(CACHE): Detail Komentar dengan ID = ($id)", $result);
            }

            $comment = Comment::find($id);
            if ($comment) {
                $createdBy = User::select('name')->find($comment->created_by);
                $editedBy = User::select('name')->find($comment->edited_by);
                // $ctgComment = CtgComment::select('id', 'title_ctg', 'slug')->find($comment->ctg_comment_id);
                $topics = $comment->topics()->select('id', 'title', 'slug')->get();

                $comment->created_by = optional($createdBy)->only(['name']);
                $comment->edited_by = optional($editedBy)->only(['name']);
                // $comment->ctg_comment_id = optional($ctgComment)->only(['id', 'title_ctg', 'slug']);
                $comment->topics = $topics->map(function ($topic) {
                    return $topic->only(['id', 'title', 'slug']);
                });

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($comment));
                return $this->success("Detail Komentar dengan ID = ($id)", $comment);
            } else {
                return $this->error("Not Found", "Komentar dengan ID = ($id) tidak ditemukan!", 404);
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
                'comment' =>  'required',
                'media_id' =>  'required',
            ],
            [
                'comment.required' => 'Mohon masukkan nama komentar!',
                'media_id.required' => 'media_id tidak boleh kosong!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $mediaId = $request->media_id;
            $comment = new Comment();
            $comment->comment = $request->comment;
            $comment->parent_id = $request->parent_id ? $request->parent_id : null;
            $comment->media_id = $mediaId;
            $comment->posted_at = Carbon::now();
            $comment->report_stat = 'Normal'; //default

            $user = Auth::user();
            $comment->user_id = $user->id;
            $comment->created_by = $user->id;
            $comment->edited_by = $user->id;

            // save
            $create = $comment->save();

            if ($create) {
                Media::where('id', $mediaId)->increment('comment_count');
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("Komentar Berhasil ditambahkan!", $comment);
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
                return $this->error("Not Found", "Komentar dengan ID = ($id) tidak ditemukan!", 404);
            }


            // approved
            $comment['title_comment'] = $request->title_comment ?? $comment->title_comment;
            $comment['description'] = $request->description ?? $comment->description;
            $comment['ytb_url'] = $request->ytb_url ?? $comment->ytb_url;
            $comment['report_stat'] = $request->report_stat ?? $comment->report_stat;

            $ctg_comment_id = $request->ctg_comment_id;
            // $ctg = CtgComment::where('id', $ctg_comment_id)->first();
            // if ($ctg) {
            //     $comment['ctg_comment_id'] = $ctg_comment_id ?? $comment->ctg_comment_id;
            // } else {
            //     return $this->error("Tidak ditemukan!", "Kategori comment dengan ID = ($ctg_comment_id) tidak ditemukan!", 404);
            // }

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
                return $this->success("Komentar Berhasil diperbaharui!", $comment);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // delete
    public function deleteComment($id)
    {
        try {
            $comment = Comment::find($id);
            if (!$comment) {
                return $this->error("Not Found", "Komentar dengan ID = ($id) tidak ditemukan!", 404);
            }

            $mediaId = $comment->media_id;
            $del = $comment->delete();
            if ($del) {
                if ($mediaId) {
                    Media::where('id', $mediaId)->decrement('comment_count');
                }
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Komentar dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}