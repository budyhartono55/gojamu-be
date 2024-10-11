<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;

class Ctg_Information extends Model
{
    use HasFactory, GenUid;

    protected $guarded = [];
    protected $table = "ctg_informations";

    // R E L A T I O N ==============
    public function informations()
    {
        return $this->hasMany(Information::class, 'ctg_information_id', 'id');
    }
    public function pemohon()
    {
        return $this->hasMany(Pemohon::class, 'ctg_pemohon_id', 'id');
    }

    // general
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
