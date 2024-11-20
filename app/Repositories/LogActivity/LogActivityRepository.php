<?php

namespace App\Repositories\LogActivity;


use App\Models\LogActivity;
use App\Repositories\LogActivity\LogActivityInterface;
use App\Traits\API_response;
use Illuminate\Support\Facades\Redis;
use App\Helpers\Helper;
use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Auth;

class LogActivityRepository implements LogActivityInterface
{
    private $logActivity;

    // 1 Day redis expired
    private $expired = 3600;
    private $keyRedis = "logActivity_";
    use API_response;

    public function __construct(LogActivity $logActivity)
    {
        $this->logActivity = $logActivity;
    }

    public function getLogActivity($request)
    {

        try {
            LogHelper::deleteToLog();

            // Extract and process request parameters
            $limit = Helper::limitDatas($request);
            $getById = $request->id;
            $getByUserId = $request->user_id;
            $getByUsername = $request->username;
            $getByAktifitas = $request->aktifitas;
            $paginate = $request->paginate;
            $order = $request->filled('order') ? 'asc' : 'desc';
            $paramsString = http_build_query([
                'Id' => $getById,
                'User' => $getByUserId,
                'Username' => $getByUsername,
                'Aktifitas' => $getByAktifitas,
                'Paginate' => $paginate,
                'Order' => $order,
                'Limit' => $limit
            ], '', ',#');

            // Generate Redis key
            $key = $this->keyRedis . "All" . $request->get('page', 1) . $paramsString;

            // Check Redis cache
            if (Redis::exists($key)) {
                $result = json_decode(Redis::get($key));
                return $this->success("Daftar LogActivity User {$paramsString} from (CACHE)", $result);
            }

            // Build query
            $query = LogActivity::orderBy('created_at', $order);

            if ($request->filled('username') && Auth::user()->role === 'Admin') {
                $query->where('username', 'LIKE', "%{$getByUsername}%");
            }

            if ($request->filled('aktifitas') && Auth::user()->role === 'Admin') {
                $query->where('subject', 'LIKE', "%{$getByAktifitas}%");
            }

            if (Auth::user()->role !== 'Admin') {
                $query->where('user_id', Auth::id());
            }

            if ($request->filled('id')) {
                $query->where('id', $getById);
            }
            if ($request->filled('user_id')) {
                $query->where('user_id', $getByUserId);
            }

            // Fetch results
            $result = $paginate === 'true'
                ? $query->paginate($limit)
                : $query->take($limit)->get();

            // Cache results in Redis
            if ($result) {
                Redis::set($key, json_encode($result));
                Redis::expire($key, 60); // Cache for 60 seconds
                return $this->success("Daftar LogActivity User {$paramsString}", $result);
            }

            return $this->success("Daftar logActivity tidak ditemukan", []);
        } catch (\Exception $e) {
            return $this->error("Internal Server Error!", $e->getMessage());
        }
    }
}
