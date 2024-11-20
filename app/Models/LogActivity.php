<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use App\Traits\GenUid;
use Illuminate\Database\Eloquent\Concerns\HasUuids;



class LogActivity extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'log_activity';

    protected $guarded = ['id'];
}
