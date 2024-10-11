<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\GenUid;

class Pilar extends Model
{
    use HasFactory, GenUid;

    protected $guarded = [];
    // protected $fillable = ['pilar_title','description','image'];

    public function application()
    {
        return $this->hasMany(Application::class);
    }
}