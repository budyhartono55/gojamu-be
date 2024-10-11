<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TTGServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {

        // S E T T I N G ================
        $this->app->bind(
            'App\Repositories\Setting\SettingInterface',
            'App\Repositories\Setting\SettingRepository'
        );

        // U S E R  ================
        $this->app->bind(
            'App\Repositories\User\UserInterface',
            'App\Repositories\User\UserRepository'
        );

        // C A T E G O R Y N E W S================
        $this->app->bind(
            'App\Repositories\CategoryNews\CategoryNewsInterface',
            'App\Repositories\CategoryNews\CategoryNewsRepository'
        );

        // N E W S ================
        $this->app->bind(
            'App\Repositories\News\NewsInterface',
            'App\Repositories\News\NewsRepository'
        );

        // A C H I E V E M E N T ================
        $this->app->bind(
            'App\Repositories\Achievement\AchievementInterface',
            'App\Repositories\Achievement\AchievementRepository'
        );

        // L I A I S O N ================
        $this->app->bind(
            'App\Repositories\Liaison\LiaisonInterface',
            'App\Repositories\Liaison\LiaisonRepository'
        );

        // A U T H ================
        $this->app->bind(
            'App\Repositories\Auth\AuthInterface',
            'App\Repositories\Auth\AuthRepository'
        );

        // CTG_SERVICE ================
        $this->app->bind(
            'App\Repositories\Ctg_Service\Ctg_ServiceInterface',
            'App\Repositories\Ctg_Service\Ctg_ServiceRepository'
        );

        // SERVICE ================
        $this->app->bind(
            'App\Repositories\Service\ServiceInterface',
            'App\Repositories\Service\ServiceRepository'
        );

        // EVENT ================
        $this->app->bind(
            'App\Repositories\Event_Program\Event_ProgramInterface',
            'App\Repositories\Event_Program\Event_ProgramRepository'
        );

        // AGENDA ================
        $this->app->bind(
            'App\Repositories\Agenda\AgendaInterface',
            'App\Repositories\Agenda\AgendaRepository'
        );

        // BASE ================
        $this->app->bind(
            'App\Repositories\Base\BaseInterface',
            'App\Repositories\Base\BaseRepository'
        );

        // ENTRANT ================
        $this->app->bind(
            'App\Repositories\Entrant\EntrantInterface',
            'App\Repositories\Entrant\EntrantRepository'
        );

        // CONTEST ================
        $this->app->bind(
            'App\Repositories\Contest\ContestInterface',
            'App\Repositories\Contest\ContestRepository'
        );

        // CTG_GALLERY ================
        $this->app->bind(
            'App\Repositories\Ctg_Gallery\Ctg_GalleryInterface',
            'App\Repositories\Ctg_Gallery\Ctg_GalleryRepository'
        );

        // G A L L E R Y ================
        $this->app->bind(
            'App\Repositories\Gallery\GalleryInterface',
            'App\Repositories\Gallery\GalleryRepository'
        );

        // A N N O U N C E M E N T ================
        $this->app->bind(
            'App\Repositories\Announcement\AnnouncementInterface',
            'App\Repositories\Announcement\AnnouncementRepository'
        );

        // S P O N S O R================
        $this->app->bind(
            'App\Repositories\Sponsor\SponsorInterface',
            'App\Repositories\Sponsor\SponsorRepository'
        );


        // G E T F I L E ================
        $this->app->bind(
            'App\Repositories\GetFile\GetFileInterface',
            'App\Repositories\GetFile\GetFileRepository'
        );

        // P R O V I N S I ================
        $this->app->bind(
            'App\Repositories\Wilayah\Provinsi\ProvinsiInterface',
            'App\Repositories\Wilayah\Provinsi\ProvinsiRepository'
        );

        // K A B U P A T E N================
        $this->app->bind(
            'App\Repositories\Wilayah\Kabupaten\KabupatenInterface',
            'App\Repositories\Wilayah\Kabupaten\KabupatenRepository'
        );

        // K E C A M A T A N================
        $this->app->bind(
            'App\Repositories\Wilayah\Kecamatan\KecamatanInterface',
            'App\Repositories\Wilayah\Kecamatan\KecamatanRepository'
        );

        // D E S A================
        $this->app->bind(
            'App\Repositories\Wilayah\Desa\DesaInterface',
            'App\Repositories\Wilayah\Desa\DesaRepository'
        );
    }
}
