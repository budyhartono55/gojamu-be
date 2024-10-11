<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

//SYNC OLD
// use App\Jobs\Job_InsertInformation;
// use App\Jobs\Job_UpdateInformation;
// use App\Jobs\Job_DropInformation;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('sync:information')->everySixHours();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
