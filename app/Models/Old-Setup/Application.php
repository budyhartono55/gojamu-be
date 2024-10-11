<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;

class Application extends Model
{
    use HasFactory, GenUid;

    protected $guarded = [];
    // protected $fillable = ['pilar_title','description','image'];

    public function pilar()
    {
        return $this->belongsToMany(Pilar::class);
    }

}