<?php

namespace App\Models;

use App\Models\Wilayah\Kabupaten;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Entrant extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'entrant';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function events()
    {
        return $this->belongsTo(Event_Program::class, 'event_id', 'id');
    }
    public function achievements()
    {
        return $this->hasMany(Achievement::class, 'entrant_id', 'id');
    }
    public function bases()
    {
        return $this->belongsTo(Base::class, 'base_id', 'id');
    }
    public function contests()
    {
        return $this->belongsTo(Contest::class, 'contest_id', 'id');
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

    public function kabupaten()
    {
        return $this->belongsTo(Kabupaten::class, 'asal_kab_id');
    }
}
