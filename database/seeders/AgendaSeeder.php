<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Agenda;
use Illuminate\Support\Facades\Auth;

class AgendaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $agendaData = [
            [
                'agenda_title' => 'Agenda 1',
                'description' => 'Deskripsi agenda 1',
                'hold_on' => Carbon::createFromFormat('Y-m-d', '2023-05-16'),
                'posted_at' => Carbon::now(),
                'image' => 'gambar1.jpg',
                // 'user_id' => Auth::id(),
                'user_id' => 1,
            ],
            [
                'agenda_title' => 'Agenda 2',
                'description' => 'Deskripsi agenda 2',
                'hold_on' => Carbon::createFromFormat('Y-m-d', '2023-05-18'),
                'posted_at' => Carbon::now(),
                'image' => 'gambar2.jpg',
                // 'user_id' => Auth::id(),
                'user_id' => 2,
            ],
        ];

        // Looping untuk menyimpan data agenda ke database
        foreach ($agendaData as $data) {
            $agenda = new Agenda();
            $agenda->agenda_title = $data['agenda_title'];
            $agenda->description = $data['description'];
            $agenda->hold_on = $data['hold_on'];
            $agenda->posted_at = $data['posted_at'];
            $agenda->image = $data['image'];
            $agenda->user_id = $data['user_id'];
            $agenda->save();
        }
        
    }
}