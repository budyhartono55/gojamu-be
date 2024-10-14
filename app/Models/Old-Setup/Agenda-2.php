<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Agenda extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'agenda';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function events()
    {
        return $this->belongsTo(Event_Program::class, 'event_id', 'id');
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
}
