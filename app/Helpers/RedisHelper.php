<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;


class RedisHelper
{
    public static function dropKeys($keyword)
    {
        if (Redis::keys("*Dashboard-countData")) {
            Redis::del("Dashboard-countData");
        }
        $keys = array_merge(
            Redis::keys('*' . $keyword . '*'),
            // Redis::keys('*' . 'admin_' . $keyword . '*'), 
        );

        if (!empty($keys)) {
            $keys = array_map(fn ($k) => str_replace(env('REDIS_KEY'), '', $k), $keys);
            Redis::del($keys);
        }
    }
}
