<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $user = [

        //     'name' => 'dev',
        //     'username' => 'developer',
        //     'email' => 'operator@tes.com',
        //     'address' => 'Sumbawa',
        //     'contact' => '8798734879',
        //     'password' => bcrypt('dev123'),
        //     'level' => "Admin",


        // ];

        // foreach ($user as $key => $value) {
        //     User::create($value);
        // }

        DB::table('users')->insert([
            'id' => '275a3671-7662-4062-8d6b-9831f7e24827',
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'address' => 'Sumbawa Barat',
            'jenis_kelamin' => 'Laki-laki',
            'contact' => '8798734879',
            'password' => bcrypt('Qwerty123456!'),
            'role' => 'Admin',
            'id_belajar' => 'Tidak',
            'active' => 1,
            'created_at' => now(),

        ]);
    }
}
