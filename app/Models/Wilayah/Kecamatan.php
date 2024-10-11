<?php

namespace App\Models\Wilayah;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kecamatan extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'kecamatan';
    public $timestamps = false;

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
