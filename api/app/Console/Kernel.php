<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     */
    protected $commands = [
        \App\Console\Commands\GetAllDeviceStatus::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Run at 8:00 AM, 11:00 AM, and 3:00 PM every day
        $schedule->command('app:get-all-device-status')
            ->cron('0 8,11,15 * * *')
            ->appendOutputTo(storage_path('logs/device_status.log'));
    }

    /**
     * Register the closure-based commands for the application.
     */
    protected function commands()
    {
        // Load commands from routes/console.php (optional)
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
