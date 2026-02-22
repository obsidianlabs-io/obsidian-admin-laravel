<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rbac:doctor')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('audit:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();

if (class_exists(\Laravel\Horizon\Horizon::class)) {
    Schedule::command('horizon:snapshot')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

if (class_exists(\Laravel\Pulse\Pulse::class)) {
    Schedule::command('pulse:check')
        ->everyMinute()
        ->withoutOverlapping();
}
