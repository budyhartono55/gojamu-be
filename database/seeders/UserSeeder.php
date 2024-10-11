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
            'id' => '343435fdgdg45451',
            'name' => 'dev',
            'username' => 'dev',
            'email' => 'operator@tes.com',
            'address' => 'Sumbawa Barat',
            'contact' => '8798734879',
            'password' => bcrypt('dev123'),
        ]);
    }
}
