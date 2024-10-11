<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class hasLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $user = session()->has('smartcity_token');

        if ($user) {
            $res = Http::accept('application/json')->withToken(
                session()->get('smartcity_token')
            )->get(env('URL_LOCAL_API') . '/cekLogin');
            if ($res) {
                return $next($request);
            }
            return redirect('login');
        }
        return redirect('login');
    }
}
