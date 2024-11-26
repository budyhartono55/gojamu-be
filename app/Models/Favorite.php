<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Favorite extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'favorite';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    //R E L A T I O N ==============
    public function books()
    {
        return $this->belongsTo(Book::class, 'book_id', 'id');
    }
    public function media()
    {
        return $this->belongsTo(Media::class, 'content_id', 'id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
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