<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;

class Ctg_Berkas extends Model
{
    use HasFactory, GenUid;

    protected $guarded = [];
    protected $table = "ctg_berkas";

    // R E L A T I O N ==============
    public function berkas()
    {
        return $this->hasMany(Berkas_Dinsos::class, 'ctg_berkas_id', 'id');
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
