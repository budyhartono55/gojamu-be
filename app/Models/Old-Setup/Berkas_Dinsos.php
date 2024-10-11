<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Berkas_Dinsos extends Model
{
    use SoftDeletes, HasFactory, GenUid;

    protected $guarded = [];
    protected $table = 'berkas';

    //R E L A T I O N ==============
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ctgBerkas()
    {
        return $this->belongsTo(Ctg_Berkas::class, 'ctg_berkas_id');
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
