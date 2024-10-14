<?php

namespace App\Models;

use App\Models\Wilayah\Kabupaten;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Liaison extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    protected $guarded = [];
    protected $table = 'liaison';
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    protected $dates = ['deleted_at'];


    // relasi one to many (comment)

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kab_id');
    }

    public function event()
    {
        return $this->belongsTo(Event_Program::class, 'event_id');
    }


    //================================================
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
