<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Information extends Model
{
    use SoftDeletes, HasFactory, GenUid;

    protected $guarded = [];
    protected $table = 'informations';

    //R E L A T I O N ==============
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ctgInformation()
    {
        return $this->belongsTo(Ctg_Information::class, 'ctg_information_id');
    }


    // general
    public function userId()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
