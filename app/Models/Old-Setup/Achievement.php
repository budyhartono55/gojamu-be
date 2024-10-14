<?php

namespace App\Models;

use App\Models\Wilayah\Kabupaten;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Achievement extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    protected $guarded = [];
    protected $table = 'achievement';
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'

    ];

    protected $dates = ['deleted_at'];

    public function contests()
    {
        return $this->belongsTo(Contest::class, 'contest_id', 'id');
    }
    public function entrants()
    {
        return $this->belongsTo(Entrant::class, 'entrant_id', 'id');
    }

    // relasi one to many (comment)

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'kab_id');
    }

    public function event()
    {
        return $this->belongsTo(Event_Program::class, 'event_id');
    }

    public function contest()
    {
        return $this->belongsTo(Contest::class, 'contest_id');
    }

    public function entrant()
    {
        return $this->belongsTo(Entrant::class, 'entrant_id');
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
