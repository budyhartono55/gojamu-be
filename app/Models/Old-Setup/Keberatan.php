<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\GenUid;

class Keberatan extends Model
{
    use HasFactory, GenUid, SoftDeletes;
    protected $guarded = [];
    protected $table = 'keberatan';
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];


    // relasi one to many (comment)


}
