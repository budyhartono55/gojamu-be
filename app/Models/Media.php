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
    public function likedByUsers()
    {
        return $this->belongsToMany(User::class, 'like', 'media_id', 'user_id');
    }
    public function topics()
    {
        // return $this->belongsTo(Topic::class, 'topic_id', 'id');
        return $this->belongsToMany(Topic::class, 'pivot_media_topic', 'media_id', 'topic_id', 'id');
    }
    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'media_id', 'id');
    }
    public function comments()
    {
        return $this->hasMany(Comment::class, 'media_id', 'id');
    }
    public function likes()
    {
        return $this->hasMany(Like::class, 'media_id', 'id');
    }
    public function ratings()
    {
        return $this->hasMany(Rating::class, 'media_id', 'id');
    }
    public function reports()
    {
        return $this->hasMany(Report::class, 'media_id', 'id');
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
