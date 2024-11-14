<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Traits\API_response;

class isAkses
{
    use API_response;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $aksesAllowList)
    {
        $aksesCurrent = auth()->user()->role;

        // Konversi daftar akses yang diizinkan menjadi array
        $aksesAllow = explode('|', $aksesAllowList);

        if (!in_array($aksesCurrent, $aksesAllow)) {
            return $this->error("Unauthorized", "Akses Tidak diizinkan!", 403);
        }

        // Jika role diizinkan, lanjutkan request
        return $next($request);
    }
}
