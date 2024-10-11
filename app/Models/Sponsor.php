<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Sponsor extends Model
{
    use HasFactory, HasUuids;
    protected $guarded = [];
    protected $table = 'sponsor';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    // protected $dates = ['deleted_at'];

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
