<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;
use Illuminate\Database\Eloquent\SoftDeletes;


class Pemohon extends Model
{
    use HasFactory, GenUid, SoftDeletes;

    protected $guarded = [];
    protected $table = 'pemohon';
    protected $dates = ['deleted_at'];


    //R E L A T I O N ==============
    public function ctgInformation()
    {
        return $this->belongsTo(Ctg_Information::class, 'ctg_information_id');
    }
    public function ctgPemohon()
    {
        return $this->belongsTo(Ctg_Pemohon::class, 'ctg_pemohon_id');
    }


    // general
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
