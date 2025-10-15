<?php

use App\Console\Commands\SchedulePostsCommand;
use App\Console\Commands\WebhookSecurityCommand;
use App\Console\Commands\WebhookLogsCommand;
use App\Console\Commands\WebhookHealthCommand;
use App\Console\Commands\WebhookMetricsCommand;
use App\Console\Commands\WebhookAlertsCommand;
use App\Jobs\CleanupWebhookSecurityDataJob;
use App\Jobs\CheckWebhookHealthJob;
use App\Jobs\CleanupWebhookLogsJob;
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

// Schedule webhook security cleanup job to run daily
Schedule::job(new CleanupWebhookSecurityDataJob(30))
    ->daily()
    ->description('Clean up old webhook security data')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule webhook security health check to run hourly
Schedule::command('webhook:security health')
    ->hourly()
    ->description('Check webhook security system health')
    ->withoutOverlapping()
    ->onOneServer();

// Monitoring and Observability Tasks

// Schedule webhook health checks to run every minute
Schedule::job(new CheckWebhookHealthJob())
    ->everyMinute()
    ->description('Run webhook system health checks')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule webhook log cleanup to run daily at 2 AM
Schedule::job(new CleanupWebhookLogsJob())
    ->dailyAt('02:00')
    ->description('Clean up old webhook logs')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule metrics cleanup to run daily at 3 AM
Schedule::command('webhook:metrics cleanup')
    ->dailyAt('03:00')
    ->description('Clean up old webhook metrics')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule alert evaluation to run every minute
Schedule::command('webhook:alerts evaluate')
    ->everyMinute()
    ->description('Evaluate webhook alert rules')
    ->withoutOverlapping()
    ->onOneServer();

// Schedule system metrics collection to run every 5 minutes
Schedule::command('webhook:metrics collect')
    ->everyFiveMinutes()
    ->description('Collect system metrics')
    ->withoutOverlapping()
    ->onOneServer();
