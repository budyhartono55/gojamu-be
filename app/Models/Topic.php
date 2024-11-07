<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Mail\Mailables\Content;

class Topic extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'topic';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    //R E L A T I O N ==============
    public function books()
    {
        return $this->belongsToMany(Book::class, 'book_topic', 'topic_id', 'book_id');
    }
    public function medias()
    {
        return $this->hasMany(Media::class, 'topic_id');
    }

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
