<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Contest extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = "contest";
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // R E L A T I O N ==============
    public function events()
    {
        return $this->belongsTo(Event_Program::class, 'event_id', 'id');
    }

    public function entrants()
    {
        return $this->hasMany(Entrant::class, 'contest_id', 'id');
    }

    public function achievements()
    {
        return $this->hasMany(Achievement::class, 'contest_id', 'id');
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
