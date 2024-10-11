<?php

namespace App\Models\Wilayah;

use App\Models\Base;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kabupaten extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'kabupaten';
    public $timestamps = false;


    public function bases()
    {
        return $this->hasMany(Base::class);
    }

    public function achievement()
    {
        return $this->hasMany(Achievement::class, 'kab_id');
    }
}
