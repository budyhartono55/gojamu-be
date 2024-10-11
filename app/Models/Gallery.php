<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Gallery extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = "galleries";


    public function ctg_galleries()
    {
        return $this->belongsTo(Ctg_Gallery::class, 'ctg_gallery_id');
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
