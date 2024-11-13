<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class Book extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    protected $guarded = [];
    protected $table = 'book';
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at'

    ];

    protected $dates = ['deleted_at'];


    // relasi one to many (comment)


    public function ctg_book()
    {
        return $this->belongsTo(Ctg_Book::class, 'ctg_book_id');
    }
    public function topics()
    {
        return $this->belongsToMany(Topic::class, 'book_topic', 'book_id', 'topic_id');
    }
    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'book_favorite_user')->withTimestamps()->withPivot('marked_at');
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
