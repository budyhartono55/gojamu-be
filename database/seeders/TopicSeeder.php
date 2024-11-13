<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        DB::table('topic')->insert([
            [
                'id' => '1a',
                'title' => 'Cloud Storage',
                'slug' => 'cloud-storage',
            ],
            [
                'id' => '2a',
                'title' => 'Big Data',
                'slug' => 'big-data',
            ],
            [
                'id' => '3a',
                'title' => 'Machine Learning',
                'slug' => 'machine-learning',
            ],
        ]);
    }
}