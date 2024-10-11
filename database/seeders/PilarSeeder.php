<?php

namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
 
class PilarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('pilars')->insert([
            'pilar_title' =>"Environment",
            'description' =>"Lorem ipsum dolor sit amet consectetur adipisicing elit. Culpa excepturi eveniet veritatis.",
            'image' =>"cobaimage.png",
        ]);
    }
}