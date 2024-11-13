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
        // $listAkses = ['SuperAdmin', 'Admin', 'Operator', 'Verifikator', 'Tamu'];
        $aksesAllow = explode("|", $aksesAllowList);
        if (in_array($aksesCurrent, $aksesAllow)) {
            return $next($request);
        }
        return $this->error("Unauthorized", "Akses Tidak diijinkan!", 403);
    }
}
