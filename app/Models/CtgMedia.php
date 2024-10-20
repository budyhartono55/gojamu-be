<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class CtgMedia extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = "ctg_media";
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // R E L A T I O N ==============
    public function medias()
    {
        return $this->hasMany(Media::class, 'ctg_media_id', 'id');
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
