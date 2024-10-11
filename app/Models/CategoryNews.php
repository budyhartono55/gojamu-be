<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class CategoryNews extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];
    protected $table = 'category_news';
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    //R E L A T I O N ==============
    public function news()
    {
        return $this->hasMany(News::class, 'category_id');
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
