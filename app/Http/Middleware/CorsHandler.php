<?php

namespace App\Http\Middleware;

use Closure;

class CorsHandler
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', '*');
        $response->header('Access-Control-Allow-Headers', '*');
        return $response;
    }

    // public function handle($request, Closure $next)
    // {
    //     $response = $next($request);

    //     $response->headers->set('Access-Control-Allow-Origin', 'http://localhost:3000');
    //     $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    //     $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');

    //     return $response;
    // }
}