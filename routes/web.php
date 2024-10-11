<?php

use App\Http\Controllers\GetFileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SendMailController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
        return response()->json([
                'code' => 200,
                'error' => false,
                'message' => "Welcome",
                'results' => "API LAYANAN INFORMASI"
        ]);
});
Route::get('/file', [GetFileController::class, "getFile"])->middleware('XssSanitizer');
