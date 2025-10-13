<?php

use App\Console\Commands\SchedulePostsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the social posts publishing command to run every minute
Schedule::command(SchedulePostsCommand::class)
    ->everyMinute()
    ->description('Publish scheduled social media posts')
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();
