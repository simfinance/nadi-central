<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nadi:central-health-collect')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('nadi:telemetry-retention-cleanup')
    ->dailyAt('02:30')
    ->withoutOverlapping();