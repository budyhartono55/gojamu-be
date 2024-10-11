<?php

namespace App\Models;

use App\Models\Wilayah\Kabupaten;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Base extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'base';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function events()
    {
        return $this->belongsTo(Event_Program::class, 'event_id', 'id');
    }

    public function entrants()
    {
        return $this->hasMany(Entrant::class, 'base_id', 'id');
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
