<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogApiResponse
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $statusCode = $response->status();
        if ($statusCode >= 300) {
            $logData = [
                'url' => $request->fullUrl(),
                'status_code' => $statusCode,
                'response' => $response->getContent(),
            ];
            Log::channel('api')->warning('API Response', $logData);
        }

        return $response;
    }
}