<?php

namespace App\Repositories\Comment;

use App\Repositories\Comment\CommentInterface as CommentInterface;
use App\Models\Comment;
use App\Models\User;
use App\Traits\API_response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Models\Media;
use App\Models\Report;
use Carbon\Carbon;

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

    public function getAllCommentsAttention()
    {
        try {
            $key = $this->generalRedisKeys . "public_All_attention_" . request()->get("page", 1);
            $keyAuth = $this->generalRedisKeys . "auth_All_attention_" . request()->get("page", 1);
            $key = Auth::check() ? $keyAuth : $key;

            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("(CACHE): Daftar Komentar dengan status perhatian", $result);
            }

            $comment = Comment::with([
                'createdBy',
                'editedBy',
            ])
                ->where('report_stat', 'attention')
                ->latest('created_at')
                ->paginate(12);

            if ($comment) {
                $modifiedData = $comment->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['id', 'name', 'image']);
                    $item->edited_by = optional($item->editedBy)->only(['id', 'name']);

                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                Redis::setex($key, 60, json_encode($comment));
                return $this->success("Daftar Komentar dengan status perhatian", $comment);
            }

            return $this->success("Tidak ada Komentar dengan status perhatian.", []);
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
                return $this->success("(CACHE): Detail Komentar dengan ID = ($id)", $result);
            }

            $comments = Comment::with([
                'replies' => function ($query) {
                    $query->with('users:id,name,image');
                }
            ])
                ->find($id);

            if ($comments) {
                $comments->replies = $this->nestedReplies($comments->replies);
                $createdBy = User::select('id', 'name', 'image')->find($comments->created_by);
                $editedBy = User::select('id', 'name')->find($comments->edited_by);

                $comments->created_by = optional($createdBy)->only(['id', 'name', 'image']);
                $comments->edited_by = optional($editedBy)->only(['id', 'name']);

                $key = Auth::check() ? $keyAuth . $id : $key . $id;
                Redis::setex($key, 60, json_encode($comments));
                return $this->success("Detail Komentar dengan ID = ($id)", $comments);
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
            if ($request->parent_id) {
                $parentComment = Comment::find($request->parent_id);
                if (!$parentComment) {
                    return $this->error("Not Found", "Komentar dengan Parent ID = {$request->parent_id} tidak ditemukan!", 404);
                }
                if ($parentComment->media_id !== $mediaId) {
                    return $this->error("Bad Request", "Komentar ini harus berada pada media yang sama dengan komentar induk!", 400);
                }
            }
            $comment = new Comment();
            $comment->comment = $request->comment;
            $comment->parent_id = $request->parent_id ? $request->parent_id : null;
            $comment->media_id = $mediaId;
            $comment->posted_at = Carbon::now();
            // $comment->report_stat = 'normal'; //default

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
            // search
            $comment = Comment::find($id);
            // checkID
            if (!$comment) {
                return $this->error("Tidak ditemukan", "Komentar dengan ID = ($id) tidak ditemukan!", 404);
            }

            if ($comment->user_id !== Auth::id()) {
                return $this->error("Akses bermasalah", "Anda tidak memiliki akses untuk melakukan update pada komentar ini!", 403);
            }

            $mediaId = $request->media_id;
            // approved
            $comment['comment'] = $request->comment ?? $comment->comment;
            $comment['parent_id'] = $request->parent_id ?? $comment->parent_id;
            $comment['media_id'] = $request->$mediaId ?? $comment->media_id;
            $comment['posted_at'] = $request->posted_at ?? $comment->posted_at;
            $comment['report_stat'] = $comment->report_stat ?? $comment->report_stat;

            $comment['user_id'] = $comment->user_id;
            $comment['created_by'] = $comment->created_by;
            $comment['edited_by'] = Auth::user()->id;

            //save
            $update = $comment->save();
            if ($update) {
                // Media::where('id', $mediaId)->increment('comment_count');
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

            //dropReport
            Report::where('comment_id', $id)->delete();

            $mediaId = $comment->media_id;
            $totalDeleted = $this->deleteNestedComments($comment);
            $resTerkait = $totalDeleted - 1;
            if ($mediaId) {
                Media::where('id', $mediaId)->decrement('comment_count', $totalDeleted);
            }
            RedisHelper::dropKeys($this->generalRedisKeys);

            return $this->success("COMPLETED", "Komentar dengan ID = ($id) dan $resTerkait komentar terkait berhasil dihapus! #(akumulasi $totalDeleted total komentar terhapus)");
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }

    private function deleteNestedComments($comment)
    {
        $totalDeleted = 1;
        $replies = Comment::where('parent_id', $comment->id)->get();
        foreach ($replies as $reply) {
            $totalDeleted += $this->deleteNestedComments($reply);
            $reply->delete();
        }

        $comment->delete();
        return $totalDeleted;
    }

    private function nestedReplies($replies)
    {
        return $replies->map(function ($reply) {
            $reply->created_by = [
                'name' => optional($reply->users)->name,
                'image' => optional($reply->users)->image,
            ];
            unset($reply->users);

            $reply->replies = $this->nestedReplies($reply->replies);
            return $reply;
        });
    }
}
