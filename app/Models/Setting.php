<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Setting extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'setting';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

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
