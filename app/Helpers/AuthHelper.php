<?php

namespace App\Helpers;

use App\Models\Bansos;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use App\Traits\API_response;

class AuthHelper
{
    use API_response;
    public static function isOwnerData($data)
    {
        // Get the current authenticated user
        $user = Auth::user();

        // Ensure the current user is the owner of the data (created_by field in the model)
        if ($data->created_by != $user->id) {
            // If not the owner, return an error response
            return self::error(
                "Unauthorized",
                "Anda tidak memiliki izin untuk menghapus data ini!",
                403
            );
        }

        // return true; // If the user is the owner, return true or any other value you want
    }

    public static function hasRole($role)
    {
        return auth()->user()->role === $role;
    }

    // Aliases for specific roles
    public static function isAdmin()
    {
        return self::hasRole('Admin');
    }

    public static function isGuru()
    {
        return self::hasRole('Guru');
    }

    public static function isMurid()
    {
        return self::hasRole('Murid');
    }

    public static function isUmum()
    {
        return self::hasRole('Umum');
    }



    // public static function ownerPenduduk($id, $hasId = true, $trash = false)
    // {
    //     $userKabupaten = Auth::user()->kabupaten;
    //     $userKecamatan = Auth::user()->kecamatan;
    //     $userDesa = Auth::user()->desa;
    //     $userDusun = Auth::user()->dusun;
    //     $userPeliuk = Auth::user()->peliuk;
    //     if ($hasId) {


    //         if ($trash) {
    //             $penduduk = Penduduk::onlyTrashed()->find($id);
    //         } else {
    //             $penduduk = Penduduk::find($id);
    //         }

    //         $Kabupaten = in_array("SEMUA", json_decode($userKabupaten)) ? true : ($penduduk->whereIn('kabupaten_id', json_decode($userKabupaten))->exists());
    //         $Kecamatan = in_array("SEMUA", json_decode($userKecamatan)) ? true : ($penduduk->whereIn('kecamatan_id', json_decode($userKecamatan))->exists());
    //         $Desa = in_array("SEMUA", json_decode($userDesa)) ? true : ($penduduk->whereIn('desa_id', json_decode($userDesa))->exists());
    //         $Dusun = in_array("SEMUA", json_decode($userDusun)) ? true : ($penduduk->whereIn('dusun_id', json_decode($userDusun))->exists());
    //         $Peliuk = in_array("SEMUA", json_decode($userPeliuk)) ? true : ($penduduk->whereIn('peliuk_id', json_decode($userPeliuk))->exists());
    //         if ($Kabupaten and $Kecamatan and $Desa and $Dusun and $Peliuk) {
    //             return false;
    //         }
    //         return true;
    //     } else {
    //         $Kabupaten = (in_array("SEMUA", json_decode($userKabupaten)) or in_array($id->kabupaten_id, json_decode($userKabupaten))) ? true : false;
    //         $Kecamatan = (in_array("SEMUA", json_decode($userKecamatan)) or in_array($id->kecamatan_id, json_decode($userKecamatan))) ? true : false;
    //         $Desa = (in_array("SEMUA", json_decode($userDesa)) or in_array($id->desa_id, json_decode($userDesa))) ? true : false;
    //         $Dusun = (in_array("SEMUA", json_decode($userDusun)) or in_array($id->dusun_id, json_decode($userDusun))) ? true : false;
    //         $Peliuk = (in_array("SEMUA", json_decode($userPeliuk)) or in_array($id->peliuk_id, json_decode($userPeliuk))) ? true : false;
    //         // return $Dusun;
    //         if ($Kabupaten and $Kecamatan and $Desa and $Dusun and $Peliuk) {
    //             return false;
    //         }
    //         return true;
    //     }
    // }

    // public static function ownerBansos($id, $hasId = true, $trash = false)
    // {
    //     $userKabupaten = Auth::user()->kabupaten;
    //     $userKecamatan = Auth::user()->kecamatan;
    //     $userDesa = Auth::user()->desa;
    //     $userDusun = Auth::user()->dusun;
    //     $userPeliuk = Auth::user()->peliuk;



    //     if ($hasId) {
    //         $bansos = Bansos::find($id);
    //         $bansosTrash = Bansos::onlyTrashed()->find($id);

    //         if ($trash and $bansosTrash) {
    //             $penduduk = Bansos::onlyTrashed()->with('penduduk')->where('id', $id)->first()->penduduk;
    //         } else if ($bansos) {
    //             $penduduk = Bansos::with('penduduk')->where('id', $id)->first()->penduduk;
    //         }
    //         $Kabupaten = in_array("SEMUA", json_decode($userKabupaten)) ? true : (in_array($penduduk->kabupaten_id, json_decode($userKabupaten)));
    //         $Kecamatan = in_array("SEMUA", json_decode($userKecamatan)) ? true : (in_array($penduduk->kecamatan_id, json_decode($userKecamatan)));
    //         $Desa = in_array("SEMUA", json_decode($userDesa)) ? true : (in_array($penduduk->desa_id, json_decode($userDesa)));
    //         $Dusun = in_array("SEMUA", json_decode($userDusun)) ? true : (in_array($penduduk->dusun_id, json_decode($userDusun)));
    //         $Peliuk = in_array("SEMUA", json_decode($userPeliuk)) ? true : (in_array($penduduk->peliuk_id, json_decode($userPeliuk)));
    //         if ($Kabupaten and $Kecamatan and $Desa and $Dusun and $Peliuk) {
    //             return false;
    //         }
    //         return true;
    //     } else {

    //         $penduduk = Penduduk::where('id', $id->penduduk_id)->first();

    //         $Kabupaten = in_array("SEMUA", json_decode($userKabupaten)) ? true : (in_array($penduduk->kabupaten_id, json_decode($userKabupaten)));
    //         $Kecamatan = in_array("SEMUA", json_decode($userKecamatan)) ? true : (in_array($penduduk->kecamatan_id, json_decode($userKecamatan)));
    //         $Desa = in_array("SEMUA", json_decode($userDesa)) ? true : (in_array($penduduk->desa_id, json_decode($userDesa)));
    //         $Dusun = in_array("SEMUA", json_decode($userDusun)) ? true : (in_array($penduduk->dusun_id, json_decode($userDusun)));
    //         $Peliuk = in_array("SEMUA", json_decode($userPeliuk)) ? true : (in_array($penduduk->peliuk_id, json_decode($userPeliuk)));
    //         if ($Kabupaten and $Kecamatan and $Desa and $Dusun and $Peliuk) {
    //             return false;
    //         }
    //         return true;
    //     }
    // }
}
