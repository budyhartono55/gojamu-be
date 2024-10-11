<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Event_Program extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'event_program';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function agendas()
    {
        return $this->hasMany(Agenda::class, 'event_id', 'id');
    }
    public function bases()
    {
        return $this->hasMany(Base::class, 'event_id', 'id');
    }

    public function contests()
    {
        return $this->hasMany(Contest::class, 'event_id', 'id');
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

    public function kecamatan()
    {
        return $this->belongsTo(Kecamatan::class, 'district_id');
    }

    public function news()
    {
        return $this->hasMany(News::class, 'event_id');
    }
    public function announcement()
    {
        return $this->hasMany(Announcement::class, 'event_id');
    }
    public function achievement()
    {
        return $this->hasMany(Achievement::class, 'event_id');
    }
}
