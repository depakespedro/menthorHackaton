<?php

namespace App\Console;

use App\Console\Commands\BotSendImage;
use App\Console\Commands\BotSendKeyBoard;
use App\Console\Commands\BotSendMessage;
use App\Console\Commands\SyncEmails;
use App\Console\Commands\LinkedRespondentsSegments;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        BotSendMessage::class,
        BotSendKeyBoard::class,
        BotSendImage::class,
        SyncEmails::class,
        LinkedRespondentsSegments::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
         $schedule->command('syncEmails')
                  ->everyMinute();

        $schedule->command('linkedrespondentssegments')
            ->everyFiveMinutes();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
