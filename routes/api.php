<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AnnouncementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\InformationController;
use App\Http\Controllers\Api\CategoryNewsController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\Ctg_ServiceController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\Event_ProgramController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Api\EntrantController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\Ctg_GalleryController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\LiaisonController;
use App\Http\Controllers\Api\SponsorController;
use App\Http\Controllers\Api\Wilayah\DesaController;
use App\Http\Controllers\Api\Wilayah\KabupatenController;
use App\Http\Controllers\Api\Wilayah\KecamatanController;

use App\Http\Controllers\Api\Wilayah\ProvinsiController;
use App\Models\Achievement;

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
        // Route::post('register', [AuthController::class, "register"]);
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
    // U S E R
    // GET
    Route::get("/user", [UserController::class, "getAll"])->name("getAllUser");
    Route::get("/user/trash", [UserController::class, "getAllTrash"])->name("getAllTrashUser");
    Route::get("/user/{id}", [UserController::class, "getById"])->name("getByIdUser");
    Route::get("/user/restore", [UserController::class, "restore"])->name("getrestoreUser");
    Route::get("/user/restore/{id}", [UserController::class, "restoreById"])->name("getrestoreByIdUser");

    // POST
    Route::post("/user", [UserController::class, "save"])->name("createUser");

    // PATCH
    Route::patch("/user/{id}", [UserController::class, "update"])->name("updateUser");
    Route::patch("/user/password/{id}", [UserController::class, "changePassword"])->name("changePassword");

    // DELETE SEMENTARA
    Route::delete("/user/{id}", [UserController::class, "deleteSementara"])->name("deleteUserSementara");
    // DELETE PERMANENT
    Route::delete("/user/permanent/{id}", [UserController::class, "deletePermanent"])->name("deleteUserPermanent");
    // RESET
    Route::patch("/user/reset/{id}", [UserController::class, "resetPassword"])->name("resetPassword");

    // =======================================================================================================
    // N E W S
    // GET
    Route::get('/news', [NewsController::class, "getNews"])->name("news");
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
    // A C H I E V E M E N T 
    // GET
    Route::get('/achievement', [AchievementController::class, "getachievement"])->name("achievement");
    // POST
    Route::post("/achievement", [AchievementController::class, "save"])->name("createachievement");
    Route::post("/achievement/restore", [AchievementController::class, "restore"])->name("restoreachievement");
    Route::post("/achievement/restore/{id}", [AchievementController::class, "restoreById"])->name("restoreByIdachievement");

    // PATCH
    Route::patch("/achievement/{id}", [AchievementController::class, "update"])->name("editachievement");
    // DELETE
    Route::delete("/achievement/{id}", [AchievementController::class, "delete"])->name("deleteachievement");
    // DELETE PERMANENT
    Route::delete("/achievement/permanent/{id}", [AchievementController::class, "deletePermanent"])->name("deleteachievementPermanent");

    // =======================================================================================================
    // S P O N S O R
    // GET
    Route::get('/sponsor', [SponsorController::class, "getsponsor"])->name("sponsor");
    // POST
    Route::post("/sponsor", [SponsorController::class, "save"])->name("createsponsor");
    // PATCH
    Route::patch("/sponsor/{id}", [SponsorController::class, "update"])->name("editsponsor");
    // DELETE
    Route::delete("/sponsor/{id}", [SponsorController::class, "delete"])->name("deletesponsor");

    // =======================================================================================================
    // A N N O U N C E M E N T
    // GET
    Route::get('/announcement', [AnnouncementController::class, "getAnnouncement"])->name("announcement");
    // POST
    Route::post("/announcement", [AnnouncementController::class, "save"])->name("createAnnouncement");
    Route::post("/announcement/restore", [AnnouncementController::class, "restore"])->name("restoreAnnouncement");
    Route::post("/announcement/restore/{id}", [AnnouncementController::class, "restoreById"])->name("restoreByIdAnnouncement");

    // PATCH
    Route::patch("/announcement/{id}", [AnnouncementController::class, "update"])->name("editAnnouncement");
    // DELETE
    Route::delete("/announcement/{id}", [AnnouncementController::class, "delete"])->name("deleteAnnouncement");
    // DELETE PERMANENT
    Route::delete("/announcement/permanent/{id}", [AnnouncementController::class, "deletePermanent"])->name("deleteAnnouncementPermanent");

    // =======================================================================================================
    // A L I A S O N
    // GET
    Route::get('/liaison', [LiaisonController::class, "getLiaison"])->name("liaison");
    // POST
    Route::post("/liaison", [LiaisonController::class, "save"])->name("createLiaison");
    Route::post("/liaison/restore", [LiaisonController::class, "restore"])->name("restoreLiaison");
    Route::post("/liaison/restore/{id}", [LiaisonController::class, "restoreById"])->name("restoreByIdLiaison");

    // PATCH
    Route::patch("/liaison/{id}", [LiaisonController::class, "update"])->name("editLiaison");
    // DELETE
    Route::delete("/liaison/{id}", [LiaisonController::class, "delete"])->name("deleteLiaison");
    // DELETE PERMANENT
    Route::delete("/liaison/permanent/{id}", [LiaisonController::class, "deletePermanent"])->name("deleteLiaisonPermanent");


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
    Route::get("/service/dashboard", [Ctg_ServiceController::class, "dashboard"])->name("Ctg-dashboard");
    // Route::get("/service/tourism", [ServiceController::class, "tourism"])->name("tourism");
    // Route::get("/service/tourism/photo/{photo_reference}", [ServiceController::class, "tourismImage"])->name("tourism");

    // Route::get("/service/{id}", [ServiceController::class, "findById"])->name("findOne");
    // POST
    Route::post("/service", [ServiceController::class, "insert"])->name("createService");
    // PATCH
    Route::patch("/service/{id}", [ServiceController::class, "edit"])->name("editService");
    // DELETE
    Route::delete("/service/{id}", [ServiceController::class, "drop"])->name("deleteService");
    // =======================================================================================================
    // EVENT_PROGRAM
    // //GET
    Route::get("/event", [Event_ProgramController::class, "index"])->name("event");
    // POST
    Route::post("/event", [Event_ProgramController::class, "insert"])->name("createEvent_Program");
    // PATCH
    Route::patch("/event/{id}", [Event_ProgramController::class, "edit"])->name("editEvent_Program");
    // DELETE
    Route::delete("/event/{id}", [Event_ProgramController::class, "drop"])->name("deleteEvent_Program");
    // =======================================================================================================
    // Agenda
    // //GET
    Route::get("/agenda", [AgendaController::class, "index"])->name("agenda");
    // Route::get("/agenda/{id}", [AgendaController::class, "findById"])->name("findOne");
    // POST
    Route::post("/agenda", [AgendaController::class, "insert"])->name("createAgenda");
    // PATCH
    Route::patch("/agenda/{id}", [AgendaController::class, "edit"])->name("editAgenda");
    // DELETE
    Route::delete("/agenda/{id}", [AgendaController::class, "drop"])->name("deleteAgenda");
    // =======================================================================================================
    // Base
    // //GET
    Route::get("/base", [BaseController::class, "index"])->name("base");
    // Route::get("/base/{id}", [BaseController::class, "findById"])->name("findOne");
    // POST
    Route::post("/base", [BaseController::class, "insert"])->name("createBase");
    // PATCH
    Route::patch("/base/{id}", [BaseController::class, "edit"])->name("editBase");
    // DELETE
    Route::delete("/base/{id}", [BaseController::class, "drop"])->name("deleteBase");
    // =======================================================================================================
    // Entrant
    // //GET
    Route::get("/entrant", [EntrantController::class, "index"])->name("entrant");
    // Route::get("/entrant/{id}", [EntrantController::class, "findById"])->name("findOne");
    // POST
    Route::post("/entrant", [EntrantController::class, "insert"])->name("createEntrant");
    // PATCH
    Route::patch("/entrant/{id}", [EntrantController::class, "edit"])->name("editEntrant");
    // DELETE
    Route::delete("/entrant/{id}", [EntrantController::class, "drop"])->name("deleteEntrant");
    // =======================================================================================================
    // Contest
    // //GET
    Route::get("/contest", [ContestController::class, "index"])->name("contest");
    // Route::get("/contest/{id}", [ContestController::class, "findById"])->name("findOne");
    // POST
    Route::post("/contest", [ContestController::class, "insert"])->name("createContest");
    // PATCH
    Route::patch("/contest/{id}", [ContestController::class, "edit"])->name("editContest");
    // DELETE
    Route::delete("/contest/{id}", [ContestController::class, "drop"])->name("deleteContest");
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
    // POST
    Route::post("/gallery", [GalleryController::class, "add"])->name("GalleryCategory");
    Route::patch("/gallery/{id}", [GalleryController::class, "edit"])->name("editGallery");
    // DELETE
    Route::delete("/gallery/{id}", [GalleryController::class, "delete"])->name("deleteGallery");

    // =======================================================================================================
    // C A T E G O R Y  - N E W S
    // //GET
    Route::get("/category-news", [CategoryNewsController::class, "index"])->name("category");
    // Route::get("/category-news/{id}", [CategoryNewsController::class, "findById"])->name("findOne");
    // POST
    Route::post("/category-news", [CategoryNewsController::class, "add"])->name("createCategory");
    // PATCH
    Route::patch("/category-news/{id}", [CategoryNewsController::class, "edit"])->name("editCategory");
    // DELETE
    Route::delete("/category-news/{id}", [CategoryNewsController::class, "delete"])->name("deleteCategory");

    // =======================================================================================================

    // S E T T I N G 
    Route::get("/setting", [SettingController::class, "getAll"])->name("setting");
    // POST
    Route::post("/setting", [SettingController::class, "save"])->name("createSetting");
    // PATCH
    Route::patch("/setting/{id}", [SettingController::class, "update"])->name("editSetting");
    // DELETE
    Route::delete("/setting/{id}", [SettingController::class, "delete"])->name("deleteSetting");

    // =======================================================================================================
    // P R O V I N S I
    // GET
    Route::get('/provinsi', [ProvinsiController::class, "getAll"])->name("provinsi");

    // =======================================================================================================
    // K A B U P A T E N
    // GET
    Route::get('/kabupaten', [KabupatenController::class, "getAll"])->name("kabupaten");

    // =======================================================================================================
    // K E C A M A T A N
    // GET
    Route::get('/kecamatan', [KecamatanController::class, "getAll"])->name("Kecamatan");

    // D E S A
    // GET
    Route::get('/desa', [DesaController::class, "getAll"])->name("Desa");
});
// =======================================================================================================

// PUBLIC
Route::group(['middleware' => ['LogApiResponse', 'XssSanitizer']], function () {
    // CTG_Service
    Route::get("/public/ctg-service", [Ctg_ServiceController::class, "index"])->name("Ctg-service");
    Route::get("/public/ctg-service/{id}", [Ctg_ServiceController::class, "findById"])->name("findOne");
    // =======================================================================================================
    // Service
    Route::get("/public/service", [ServiceController::class, "index"])->name("service");
    Route::get("/public/service/dashboard", [Ctg_ServiceController::class, "dashboard"])->name("Ctg-dashboard");
    Route::get("/public/service/tourism", [ServiceController::class, "tourism"])->name("tourism");
    Route::get("/public/service/tourism/photo/{photo_reference}", [ServiceController::class, "tourismImage"])->name("tourism");


    // =======================================================================================================
    // EVENT_PROGRAM
    Route::get("/public/event", [Event_ProgramController::class, "index"])->name("event");
    // =======================================================================================================
    // Agenda
    Route::get("/public/agenda", [AgendaController::class, "index"])->name("agenda");
    // =======================================================================================================
    // Base
    Route::get("/public/base", [BaseController::class, "index"])->name("base");
    // =======================================================================================================
    // Entrant
    Route::get("/public/entrant", [EntrantController::class, "index"])->name("entrant");
    // =======================================================================================================
    // Contest
    Route::get("/public/contest", [ContestController::class, "index"])->name("contest");
    // =======================================================================================================


    // N E W S 
    // GET
    Route::get('/public/news', [NewsController::class, "getNews"])->name("news");

    // A N N O U N C E M E N T
    // GET
    Route::get('/public/announcement', [AnnouncementController::class, "getAnnouncement"])->name("announcement");

    // =======================================================================================================
    // A C H I E V E M E N T 
    // GET
    Route::get('/public/achievement', [AchievementController::class, "getachievement"])->name("achievement");

    // =======================================================================================================
    // S P O N S O R
    // GET
    Route::get('/public/sponsor', [SponsorController::class, "getsponsor"])->name("sponsor");

    // =======================================================================================================
    // A L I A S O N
    // GET
    Route::get('/public/liaison', [LiaisonController::class, "getLiaison"])->name("liaison");

    // C A T E G O R Y N E W S
    // //GET
    Route::get("/public/category-news", [CategoryNewsController::class, "index"])->name("category");
    // Route::get("/public/category-news/{id}", [CategoryNewsController::class, "findById"])->name("findOne");

    // =======================================================================================================
    // S E T T I N G 
    Route::get("/public/setting", [SettingController::class, "getAll"])->name("setting");

    // =======================================================================================================
    // G A L L E R Y
    Route::get("/public/gallery", [GalleryController::class, "index"])->name("gallery");
    // Route::get("/public/gallery/{id}", [GalleryController::class, "findById"])->name("findOne");

    // =======================================================================================================
    // C T G _ GALLERY
    Route::get("/public/ctg-gallery", [Ctg_GalleryController::class, "index"])->name("Ctg-gallery");
    Route::get("/public/ctg-gallery/{id}", [Ctg_GalleryController::class, "findById"])->name("findOne");

    // =======================================================================================================
    // P R O V I N S I
    // GET
    Route::get('/public/provinsi', [ProvinsiController::class, "getAll"])->name("provinsi");

    // =======================================================================================================
    // K A B U P A T E N
    // GET
    Route::get('/public/kabupaten', [KabupatenController::class, "getAll"])->name("kabupaten");

    // =======================================================================================================
    // K E C A M A T A N
    // GET
    Route::get('/public/kecamatan', [KecamatanController::class, "getAll"])->name("Kecamatan");

    // D E S A
    // GET
    Route::get('/public/desa', [DesaController::class, "getAll"])->name("Desa");
});
