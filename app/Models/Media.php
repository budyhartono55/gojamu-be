<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wilayah\Kecamatan;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'media';
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function ctgMedias()
    {
        return $this->belongsTo(CtgMedia::class, 'ctg_media_id', 'id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function topics()
    {
        return $this->belongsTo(Topic::class, 'topic_id', 'id');
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'media_id', 'id');
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
