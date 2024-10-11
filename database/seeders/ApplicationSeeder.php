<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('pilars')->insert([
            'applications_title' =>"Environment",
            'description' =>"Lorem ipsum dolor sit amet consectetur adipisicing elit. Culpa excepturi eveniet veritatis.",
            'url' =>"appurl",
            'image' =>"cobaimage.png",
        ]);
    }
}