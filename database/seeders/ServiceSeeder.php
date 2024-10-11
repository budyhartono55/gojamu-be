<?php
 
namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
 
class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        DB::table('services')->insert([
            'layanan_title' =>"Cloud Storage",
            'url' =>"http://example.com",
            'description' =>"Lorem Ipsum is simply dummy text of the printing and typesetting industry.",
            'icon' =>"coba_icon.png",
            'created_by' =>"admin",
        ]);
    }
}