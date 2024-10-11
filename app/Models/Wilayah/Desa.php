<?php

namespace App\Models\Wilayah;

use App\Models\Penduduk;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Desa extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'desa';
    public $timestamps = false;

    public function stunting_kabupaten()
    {
        return $this->hasMany(Stunting_Kabupaten::class);
    }
    public function stunting_kecamatan()
    {
        return $this->hasMany(Stunting_Kecamatan::class);
    }
}