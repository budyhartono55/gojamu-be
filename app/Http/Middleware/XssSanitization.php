<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use HTMLPurifier_Config;
use HTMLPurifier;

class XssSanitization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $allowField = "")
    {

        if ($allowField !== "") {

            $allowFieldList = [
                'description',
                'caption',
                'about',
                'visi',
                'misi',
                'caption_vm',
                'maklumat_pelayanan',
                'tugas_dan_fungsi',
                'sop_ppidkab',
                'profil_pimpinan',
                'moto',
                'alasan'
            ];
            // $fieldExcept = explode("|", $allowField);
            $input = $request->except($allowFieldList);
            array_walk_recursive($input, function (&$input) {
                $input = strip_tags($input);
            });
            $resultExcept = $request->merge($input);
            $inputOnly = $resultExcept->only($allowFieldList);
            array_walk_recursive($inputOnly, function (&$inputOnly) {
                // $inputOnly = strip_tags($inputOnly, '<script><h1><h2><p><strong><em>');
                $config =  HTMLPurifier_Config::createDefault();
                $listed = 'div,br,ol,li,ul,a[href|title],span,h1,h2,h3,h4,h5,h6,p,strong,em,table,tr,th,td,code,blockquote,s,u';
                $config->set('HTML.Allowed', $listed);
                $purifier = new HTMLPurifier($config);
                $inputOnly =  $purifier->purify($inputOnly);
            });
            $request->merge($inputOnly, $input);
        } else {
            $input = $request->all();
            array_walk_recursive($input, function (&$input) {
                $input = strip_tags($input);
            });
            $request->merge($input);
        }
        return $next($request);
    }
}
