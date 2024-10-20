<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class GojamuServiceProvider extends ServiceProvider
{
    public function boot() {}

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

        // CTG_MEDIA ================
        $this->app->bind(
            'App\Repositories\CtgMedia\CtgMediaInterface',
            'App\Repositories\CtgMedia\CtgMediaRepository'
        );

        // MEDIA ================
        $this->app->bind(
            'App\Repositories\Media\MediaInterface',
            'App\Repositories\Media\MediaRepository'
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
