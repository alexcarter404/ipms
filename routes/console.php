<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep billing exchange rates fresh (ECB publishes around 16:00 CET).
Schedule::command('billing:sync-rates')->weekdays()->dailyAt('16:30');
