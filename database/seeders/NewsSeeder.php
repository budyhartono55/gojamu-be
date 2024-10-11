<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('news')->insert([
            'berita_title' => "diskominfo website",
            'description' => "ini adalah website diskominfo",
            'slug' => 'ini-ada',
            'views' => '20',
            'image' => 'dsfdsdsf',
            'category_id' => '1,2',
            'author' => 'ahmad'
        ]);
    }
}
