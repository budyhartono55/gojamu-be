<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;

class HeaderContent extends Model
{
    use HasFactory, GenUid;

    protected $guarded = [];
    protected $table = 'header_content';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
}
