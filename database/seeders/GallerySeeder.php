<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('galleries')->insert([
            'gallery_title' =>"Gotong Royong",
            'caption' =>"Lorem ipsum dolor sit amet consectetur adipisicing elit. Culpa excepturi eveniet veritatis.",
            'image' =>"gotong.png",
            'category_id' =>1,
        ]);
    }
}