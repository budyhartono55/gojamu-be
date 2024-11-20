<?php


namespace App\Helpers;

use App\Models\LogActivity as LogActivityModel;
use Illuminate\Support\Carbon;


class LogHelper
{


    public static function addToLog($subject, $request, $status = true)
    {
        $log = [];
        $log['subject'] = $subject;
        $log['method'] = $status ? $request->method() : "";
        $log['agent'] = $status ? $request->header('user-agent') : "";
        $log['username'] = auth()->check() ? auth()->user()->username : "";
        $log['user_id'] = auth()->check() ? auth()->user()->id : 1;
        LogActivityModel::create($log);
    }

    public static function deleteToLog()
    {
        $thresholdDate = Carbon::now()->subMonths(3);

        // Delete records older than the threshold date
        LogActivityModel::where('created_at', '<', $thresholdDate)->delete();
    }
}
