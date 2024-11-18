<?php

namespace App\Repositories\Report;

use App\Repositories\Report\ReportInterface as ReportInterface;
use App\Models\Report;
use App\Models\Media;
use App\Models\Comment;
use App\Traits\API_response;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use App\Helpers\RedisHelper;
use App\Helpers\Helper;




class ReportRepository implements ReportInterface
{
    protected $report;
    protected $generalRedisKeys;

    // Response API HANDLER
    use API_response;

    public function __construct(Report $report)
    {
        $this->report = $report;
        $this->generalRedisKeys = "report_";
    }

    public function getReport($request)
    {
        $limit = Helper::limitDatas($request);
        // $getStat = $request->status;

        if (!empty($getStat)) {
            // return self::getReportStat($$getStat, $limit);
        } elseif (!empty($getKeyword)) {
            // return self::getAllServiceByKeyword($getKeyword, $limit);
        } else {
            return self::getAllReport();
        }
    }

    public function getAllReport()
    {
        try {
            $type = request()->get('type');
            $page = request()->get('page', 1);

            $keyPrefix = Auth::check() ? $this->generalRedisKeys . "auth_All_" : $this->generalRedisKeys . "public_All_";
            $key = $keyPrefix . $type . "_" . $page;
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                $cacheMessage = ($type === 'media') ?
                    "(CACHE): List Keseluruhan Laporan Media" : (($type === 'comment') ? "(CACHE): List Keseluruhan Laporan Komentar" : "(CACHE): List Keseluruhan Laporan");

                return $this->success($cacheMessage, $result);
            }

            $reportQuery = Report::with(['createdBy', 'editedBy']);
            if ($type === 'media') {
                $reportQuery->whereNotNull('media_id')->whereNull('comment_id');
            } elseif ($type === 'comment') {
                $reportQuery->whereNotNull('comment_id')->whereNull('media_id');
            }
            $report = $reportQuery->latest('created_at')->paginate(12);

            if ($report) {
                $modifiedData = $report->items();
                $modifiedData = array_map(function ($item) {
                    $item->created_by = optional($item->createdBy)->only(['name']);
                    $item->edited_by = optional($item->editedBy)->only(['name']);
                    unset($item->createdBy, $item->editedBy);
                    return $item;
                }, $modifiedData);

                Redis::setex($key, 60, json_encode($report));
                $message = ($type === 'media') ?
                    "List Keseluruhan Laporan Media" : (($type === 'comment') ? "List Keseluruhan Laporan Komentar" : "List Keseluruhan Laporan");
                return $this->success($message, $report);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }

    // create
    public function createReport($request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'reason' =>  'required',
            ],
            [
                'reason.required' => 'Mohon masukkan alasan pelaporan anda!',
            ]
        );

        if ($validator->fails()) {
            return $this->error("Terjadi Kesalahan!, Validasi Gagal.", $validator->errors(), 400);
        }

        try {
            $report = new Report();
            $user = Auth::user();
            $media_id = $request->media_id;
            $comment_id = $request->comment_id;

            $report->posted_at = Carbon::now();
            $report->media_id = $media_id;
            $report->comment_id = $comment_id;
            $report->reason = $request->reason;

            // checkMedia
            if ($media_id) {
                $checkMedia = Media::where('id', $media_id)->first();
                if (!$checkMedia) {
                    return $this->error("Tidak ditemukan!", "Media dengan ID = ($media_id) tidak ditemukan!", 404);
                }

                $report->media_id = $media_id;
                $existingReport = Report::where('media_id', $media_id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingReport) {
                    return $this->error(
                        "Laporan anda telah terdaftar",
                        "Anda telah mengirimkan laporan pada media ini, laporan Anda masih dalam review administrator.",
                        409
                    );
                }
            }

            // checkComment
            if ($comment_id) {
                $checkComment = Comment::where('id', $comment_id)->first();
                if (!$checkComment) {
                    return $this->error("Tidak ditemukan!", "Komentar dengan ID = ($comment_id) tidak ditemukan!", 404);
                }
                $report->comment_id = $comment_id;
                $existingReportCom = Report::where('comment_id', $comment_id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingReportCom) {
                    return $this->error(
                        "Laporan anda telah terdaftar",
                        "Anda telah mengirimkan laporan pada Komentar ini, laporan Anda masih dalam review administrator.",
                        409
                    );
                }
            }

            $report->user_id = $user->id;
            $report->created_by = $user->id;
            $report->edited_by = $user->id;

            if ($report->save()) {
                if (Media::where('id', $media_id)->exists()) {
                    Media::where('id', $media_id)->increment('report_count');
                    $media = Media::find($media_id);

                    if ($media->report_count > 10) {
                        $media->report_stat = 'attention';
                    } else {
                        $media->report_stat = 'normal';
                    }
                    $media->save();
                }

                if (Comment::where('id', $comment_id)->exists()) {
                    Comment::where('id', $comment_id)->increment('report_count');
                    $comment = Comment::find($comment_id);

                    if ($comment->report_count > 10) {
                        $comment->report_stat = 'attention';
                    } else {
                        $comment->report_stat = 'normal';
                    }
                    $comment->save();
                }

                // if (Media::where('id', $media_id)->exists()) {
                //     Media::where('id', $media_id)->increment('report_count');
                // }
                // if (Comment::where('id', $comment_id)->exists()) {
                //     Comment::where('id', $comment_id)->increment('report_count');
                // }
                RedisHelper::dropKeys($this->generalRedisKeys);

                return $this->success("Laporan Berhasil ditambahkan!", $report);
            } else {
                return $this->error("Gagal.", "Gagal menyimpan laporan", 401);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
    public function deleteReport($id)
    {
        try {
            // search
            $report = Report::find($id);
            if (!$report) {
                return $this->error("Not Found", "Laporan dengan ID = ($id) tidak ditemukan!", 404);
            }
            // approved
            $media_id = $report->media_id;
            $comment_id = $report->comment_id;
            $del = $report->delete();
            if ($del) {
                // Media::where('id', $mediaId)->decrement('report_count');
                if (Media::where('id', $media_id)->exists()) {
                    Media::where('id', $media_id)->decrement('report_count');
                    $media = Media::find($media_id);

                    if ($media->report_count > 10) {
                        $media->report_stat = 'attention';
                    } else {
                        $media->report_stat = 'normal';
                    }
                    $media->save();
                }

                if (Comment::where('id', $comment_id)->exists()) {
                    Comment::where('id', $comment_id)->decrement('report_count');
                    $comment = Comment::find($comment_id);

                    if ($comment->report_count > 10) {
                        $comment->report_stat = 'attention';
                    } else {
                        $comment->report_stat = 'normal';
                    }
                    $comment->save();
                }
                RedisHelper::dropKeys($this->generalRedisKeys);
                return $this->success("COMPLETED", "Laporan dengan ID = ($id) Berhasil dihapus!");
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage());
        }
    }
}
