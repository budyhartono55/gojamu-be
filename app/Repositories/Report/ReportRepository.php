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
                        "Laporan Sudah Ada",
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
                        "Laporan Sudah Ada",
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
                }
                if (Comment::where('id', $comment_id)->exists()) {
                    Comment::where('id', $comment_id)->increment('report_count');
                }
                RedisHelper::dropKeys($this->generalRedisKeys);

                return $this->success("Laporan Berhasil ditambahkan!", $report);
            } else {
                return $this->error("Gagal.", "Gagal menyimpan laporan", 401);
            }
        } catch (\Exception $e) {
            return $this->error("Internal Server Error", $e->getMessage(), 499);
        }
    }
}
