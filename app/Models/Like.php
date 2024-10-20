<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Like extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'like';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];


    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function medias()
    {
        return $this->belongsTo(Media::class, 'media_id', 'id');
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
