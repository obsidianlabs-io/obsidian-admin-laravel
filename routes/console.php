<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Horizon;
use Laravel\Pulse\Pulse;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rbac:doctor')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('audit:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();

if (class_exists(Horizon::class)) {
    Schedule::command('horizon:snapshot')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

if (class_exists(Pulse::class)) {
    Schedule::command('pulse:check')
        ->everyMinute()
        ->withoutOverlapping();
}
