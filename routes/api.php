<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Ctg_BookController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\Ctg_ServiceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\CtgMediaController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\Ctg_GalleryController;
use App\Http\Controllers\Api\Ctg_NewsController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Api\Wilayah\DesaController;
use App\Http\Controllers\Api\Wilayah\KabupatenController;
use App\Http\Controllers\Api\Wilayah\KecamatanController;
use App\Http\Controllers\Api\Wilayah\ProvinsiController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(
    ['middleware' => ['XssSanitizer']],
    function () {
        // register and login to get token
        Route::post('register', [AuthController::class, "register"]);
        Route::post('login', [AuthController::class, "login"]);

        // Route::post('forgotPassword', [AuthController::class, "forgot_password"]);
        // Route::post('resetPassword', [AuthController::class, "reset_password"]);
    }
);

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::middleware(['auth:sanctum', 'XssSanitizer:true', 'LogApiResponse'])->group(function () {
    Route::get('/token', function () {
        return response()->json([
            'code' => 200,
            'error' => false,
            'message' => "Validated",
            'results' => "Token Valid"
        ]);
    });


    Route::get('cekLogin', [AuthController::class, "cekLogin"]);

    Route::post("/logout", [AuthController::class, "logout"])->name("logout");
    Route::post("/changePassword", [AuthController::class, "change_password"])->name("change_password");

    // =======================================================================================================
    // CTG_Service
    // //GET
    Route::get("/ctg-service", [Ctg_ServiceController::class, "index"])->name("Ctg-service");
    Route::get("/ctg-service/{id}", [Ctg_ServiceController::class, "findById"])->name("findOne");
    // POST
    Route::post("/ctg-service", [Ctg_ServiceController::class, "insert"])->name("createCtg_Service");
    // PATCH
    Route::patch("/ctg-service/{id}", [Ctg_ServiceController::class, "edit"])->name("editCtg_Service");
    // DELETE
    Route::delete("/ctg-service/{id}", [Ctg_ServiceController::class, "drop"])->name("deleteCtg_Service");
    // =======================================================================================================
    // Service
    // //GET
    Route::get("/service", [ServiceController::class, "index"])->name("service");
    Route::get("/service/{id}", [ServiceController::class, "findById"])->name("findOne");
    // POST
    Route::post("/service", [ServiceController::class, "insert"])->name("createService");
    // PATCH
    Route::patch("/service/{id}", [ServiceController::class, "edit"])->name("editService");
    // DELETE
    Route::delete("/service/{id}", [ServiceController::class, "drop"])->name("deleteService");

    // =======================================================================================================
    // CTG_Media
    // //GET
    Route::get("/ctg-media", [CtgMediaController::class, "index"])->name("Ctg-media");
    Route::get("/ctg-media/{id}", [CtgMediaController::class, "findById"])->name("findOne");
    // POST
    Route::post("/ctg-media", [CtgMediaController::class, "insert"])->name("createCtgMedia");
    // PATCH
    Route::patch("/ctg-media/{id}", [CtgMediaController::class, "edit"])->name("editCtgMedia");
    // DELETE
    Route::delete("/ctg-media/{id}", [CtgMediaController::class, "drop"])->name("deleteCtgMedia");
    // =======================================================================================================
    // MEDIA
    // //GET
    Route::get("/media", [MediaController::class, "index"])->name("media");
    Route::get("/media/{id}", [MediaController::class, "findById"])->name("findOne");
    // POST
    Route::post("/media", [MediaController::class, "insert"])->name("createMedia");
    // PATCH
    Route::patch("/media/{id}", [MediaController::class, "edit"])->name("editMedia");
    // DELETE
    Route::delete("/media/{id}", [MediaController::class, "drop"])->name("deleteMedia");
    // =======================================================================================================
    // Comment
    // //GET
    // Route::get("/media/comment", [CommentController::class, "index"])->name("comment");
    Route::get("/media/comment/{id}", [CommentController::class, "findById"])->name("findOne");
    // POST
    Route::post("/media/comment", [CommentController::class, "insert"])->name("createComment");
    // PATCH
    Route::patch("/media/comment/{id}", [CommentController::class, "edit"])->name("editComment");
    // DELETE
    Route::delete("/media/comment/{id}", [CommentController::class, "drop"])->name("deleteComment");
    // =======================================================================================================

    // Report
    // //GET
    Route::get("/report", [ReportController::class, "index"])->name("report");
    Route::get("/report/{id}", [ReportController::class, "findById"])->name("findOne");
    // POST
    Route::post("/media/report", [ReportController::class, "insert"])->name("createReportMed");
    Route::post("/comment/report", [ReportController::class, "insert"])->name("createReportCom");
    // PATCH
    Route::patch("/report/{id}", [ReportController::class, "edit"])->name("editReport");
    // DELETE
    Route::delete("/report/{id}", [ReportController::class, "drop"])->name("deleteReport");
    // =======================================================================================================
    // LIKE
    // POST
    Route::post("/media/like", [LikeController::class, "insert"])->name("createLike");
    // =======================================================================================================
    // CTG_GALLERY
    // //GET
    Route::get("/ctg-gallery", [Ctg_GalleryController::class, "index"])->name("Ctg-gallery");
    Route::get("/ctg-gallery/{id}", [Ctg_GalleryController::class, "findById"])->name("findOne");
    // POST
    Route::post("/ctg-gallery", [Ctg_GalleryController::class, "add"])->name("createCtg_Gallery");
    // PATCH
    Route::patch("/ctg-gallery/{id}", [Ctg_GalleryController::class, "edit"])->name("editCtg_Gallery");
    // DELETE
    Route::delete("/ctg-gallery/{id}", [Ctg_GalleryController::class, "delete"])->name("deleteCtg_Gallery");
    // =======================================================================================================

    // G A L L E R Y
    Route::get("/gallery", [GalleryController::class, "index"])->name("gallery");
    Route::get("/gallery/{id}", [GalleryController::class, "findById"])->name("findOne");

    // POST
    Route::post("/gallery", [GalleryController::class, "add"])->name("GalleryCategory");
    Route::patch("/gallery/{id}", [GalleryController::class, "edit"])->name("editGallery");
    // DELETE
    Route::delete("/gallery/{id}", [GalleryController::class, "delete"])->name("deleteGallery");


    // =======================================================================================================
    // B O O K
    // GET
    Route::get('/book', [BookController::class, "getBook"])->name("book");
    Route::post("/book/favorite/{id}", [BookController::class, "markAsFavorite"])->name("markAsFavorite");
    Route::post("/book/unfavorite/{id}", [BookController::class, "removeFavorite"])->name("removeFavorite");
    Route::get("/book/favorite", [BookController::class, "getFavoriteBooks"])->name("getFavoriteBooks");
    // GET
    Route::get("/user", [UserController::class, "getUser"])->name("getAllUser");
    Route::get("/instruktur", [UserController::class, "instruktor"])->name("getInstruktor");
    // PATCH
    Route::patch("/user/{id}", [UserController::class, "update"])->name("updateUser");
    Route::patch("/user/password/{id}", [UserController::class, "changePassword"])->name("changePassword");
    // GET
    Route::get('/news', [NewsController::class, "getNews"])->name("news");
    Route::get("/ctg-news", [Ctg_NewsController::class, "index"])->name("ctg");
    // Route::get("/ctg-news/{id}", [CategoryNewsController::class, "findById"])->name("findOne");

    Route::get("/ctg-book", [Ctg_BookController::class, "index"])->name("ctg");
    // Route::get("/ctg_book/{id}", [CategoryNewsController::class, "findById"])->name("findOne");
    // //GET
    Route::get("/topic", [TopicController::class, "index"])->name("topic");




    Route::middleware(['isAkses:Admin'])->group(function () {

        // =======================================================================================================
        // U S E R

        // Route::get("/user/trash", [UserController::class, "getAllTrash"])->name("getAllTrashUser");
        // Route::get("/user/{id}", [UserController::class, "getById"])->name("getByIdUser");
        Route::get("/user/restore", [UserController::class, "restore"])->name("getrestoreUser");
        Route::get("/user/restore/{id}", [UserController::class, "restoreById"])->name("getrestoreByIdUser");

        // POST
        Route::post("/user", [UserController::class, "save"])->name("createUser");


        // DELETE SEMENTARA
        Route::delete("/user/{id}", [UserController::class, "deleteSementara"])->name("deleteUserSementara");
        // DELETE PERMANENT
        Route::delete("/user/permanent/{id}", [UserController::class, "deletePermanent"])->name("deleteUserPermanent");
        // RESET
        Route::patch("/user/reset/{id}", [UserController::class, "resetPassword"])->name("resetPassword");
        Route::patch("/user/status/{id}", [UserController::class, "statusUser"])->name("statusUser");

        // =======================================================================================================
        // N E W S
        // POST
        Route::post("/news", [NewsController::class, "save"])->name("createNews");
        Route::post("/news/restore", [NewsController::class, "restore"])->name("restoreNews");
        Route::post("/news/restore/{id}", [NewsController::class, "restoreById"])->name("restoreByIdNews");

        // PATCH
        Route::patch("/news/{id}", [NewsController::class, "update"])->name("editNews");
        // DELETE
        Route::delete("/news/{id}", [NewsController::class, "delete"])->name("deleteNews");
        // DELETE PERMANENT
        Route::delete("/news/permanent/{id}", [NewsController::class, "deletePermanent"])->name("deleteNewsPermanent");


        // =======================================================================================================
        // C A T E G O R Y  - N E W S
        // //GET
        // POST
        Route::post("/ctg-news", [Ctg_NewsController::class, "add"])->name("createCategory");
        // PATCH
        Route::patch("/ctg-news/{id}", [Ctg_NewsController::class, "edit"])->name("editCategory");
        // DELETE
        Route::delete("/ctg-news/{id}", [Ctg_NewsController::class, "delete"])->name("deleteCategory");

        // =======================================================================================================
        // B O O K
        // POST
        Route::post("/book", [BookController::class, "save"])->name("createBook");
        Route::post("/book/restore", [BookController::class, "restore"])->name("restoreBook");
        Route::post("/book/restore/{id}", [BookController::class, "restoreById"])->name("restoreByIdBook");

        // PATCH
        Route::patch("/book/{id}", [BookController::class, "update"])->name("editBook");
        // DELETE
        Route::delete("/book/{id}", [BookController::class, "delete"])->name("deleteBook");
        // DELETE PERMANENT
        Route::delete("/book/permanent/{id}", [BookController::class, "deletePermanent"])->name("deleteBookPermanent");


        // Route::get("/book/favorite-user/{id}", [BookController::class, "getUsersWhoFavoritedBook"])->name("getUsersWhoFavoritedBook");



        // =======================================================================================================
        // C A T E G O R Y  - B O O K
        // //GET
        // POST
        Route::post("/ctg-book", [Ctg_BookController::class, "add"])->name("createCategory");
        // PATCH
        Route::patch("/ctg-book/{id}", [Ctg_BookController::class, "edit"])->name("editCategory");
        // DELETE
        Route::delete("/ctg-book/{id}", [Ctg_BookController::class, "delete"])->name("deleteCategory");

        // =======================================================================================================
        // T O P I C
        // Route::get("/topic/{id}", [CategoryNewsController::class, "findById"])->name("findOne");
        // POST
        Route::post("/topic", [TopicController::class, "add"])->name("createTopic");
        // PATCH
        Route::patch("/topic/{id}", [TopicController::class, "edit"])->name("editTopic");
        // DELETE
        Route::delete("/topic/{id}", [TopicController::class, "delete"])->name("deleteTopic");

        // =======================================================================================================

        // S E T T I N G 
        Route::get("/setting", [SettingController::class, "getAll"])->name("setting");
        // POST
        Route::post("/setting", [SettingController::class, "save"])->name("createSetting");
        // PATCH
        Route::patch("/setting/{id}", [SettingController::class, "update"])->name("editSetting");
        // DELETE
        Route::delete("/setting/{id}", [SettingController::class, "delete"])->name("deleteSetting");
    });

    // // =======================================================================================================
    // // P R O V I N S I
    // // GET
    // Route::get('/provinsi', [ProvinsiController::class, "getAll"])->name("provinsi");

    // // =======================================================================================================
    // // K A B U P A T E N
    // // GET
    // Route::get('/kabupaten', [KabupatenController::class, "getAll"])->name("kabupaten");

    // // =======================================================================================================
    // // K E C A M A T A N
    // // GET
    // Route::get('/kecamatan', [KecamatanController::class, "getAll"])->name("Kecamatan");

    // // D E S A
    // // GET
    // Route::get('/desa', [DesaController::class, "getAll"])->name("Desa");
});
// =======================================================================================================

// PUBLIC
Route::group(['middleware' => ['LogApiResponse', 'XssSanitizer']], function () {
    // CTG_Service
    Route::get("/public/ctg-service", [Ctg_ServiceController::class, "index"])->name("Ctg-service");
    Route::get("/public/ctg-service/{id}", [Ctg_ServiceController::class, "findById"])->name("findOne");
    // ===================================================================================================
    // CTG_Service
    Route::get("/public/ctg-media", [CtgMediaController::class, "index"])->name("Ctg-media");
    Route::get("/public/ctg-media/{id}", [CtgMediaController::class, "findById"])->name("findOne");
    // ===================================================================================================
    // Media
    Route::get("/public/media", [MediaController::class, "index"])->name("media");
    Route::get("/public/media/{id}", [MediaController::class, "findById"])->name("findOne");
    // =======================================================================================================
    // Service
    Route::get("/public/service", [ServiceController::class, "index"])->name("service");
    Route::get("/public/service/{id}", [ServiceController::class, "findById"])->name("findOne");
    // =======================================================================================================


    // N E W S 
    // GET
    Route::get('/public/news', [NewsController::class, "getNews"])->name("news");
    // =======================================================================================================

    // C A T E G O R Y N E W S
    // //GET
    Route::get("/public/ctg-news", [Ctg_NewsController::class, "index"])->name("ctg");
    // Route::get("/public/ctg-news/{id}", [CategoryNewsController::class, "findById"])->name("findOne");


    // B O O K 
    // GET
    Route::get('/public/book', [BookController::class, "getBook"])->name("book");
    // =======================================================================================================

    // C A T E G O R Y B O O K
    // //GET
    Route::get("/public/ctg-book", [Ctg_BookController::class, "index"])->name("ctg-book");
    // Route::get("/public/ctg-news/{id}", [CategoryNewsController::class, "findById"])->name("findOne");


    // I N S T R U K T O R
    // //GET
    Route::get("/public/instruktur", [UserController::class, "instruktor"])->name("getInstruktor");

    // =======================================================================================================
    // S E T T I N G 
    Route::get("/public/setting", [SettingController::class, "getAll"])->name("setting");

    // =======================================================================================================
    // T O P I C
    Route::get("/public/topic", [TopicController::class, "index"])->name("topic");

    // =======================================================================================================
    // G A L L E R Y
    Route::get("/public/gallery", [GalleryController::class, "index"])->name("gallery");
    Route::get("/public/gallery/{id}", [GalleryController::class, "findById"])->name("findOne");

    // =======================================================================================================
    // C T G _ GALLERY
    Route::get("/public/ctg-gallery", [Ctg_GalleryController::class, "index"])->name("Ctg-gallery");
    Route::get("/public/ctg-gallery/{id}", [Ctg_GalleryController::class, "findById"])->name("findOne");

    // =======================================================================================================
    // P R O V I N S I
    // GET
    // Route::get('/public/provinsi', [ProvinsiController::class, "getAll"])->name("provinsi");

    // // =======================================================================================================
    // // K A B U P A T E N
    // // GET
    // Route::get('/public/kabupaten', [KabupatenController::class, "getAll"])->name("kabupaten");

    // // =======================================================================================================
    // // K E C A M A T A N
    // // GET
    // Route::get('/public/kecamatan', [KecamatanController::class, "getAll"])->name("Kecamatan");

    // // D E S A
    // // GET
    // Route::get('/public/desa', [DesaController::class, "getAll"])->name("Desa");
});
