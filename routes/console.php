<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep billing exchange rates fresh (ECB publishes around 16:00 CET).
Schedule::command('billing:sync-rates')->weekdays()->dailyAt('16:30');

// Pull inbound messages from IP office exchanges.
Schedule::command('ipo:poll')->hourly();

// Capture inbound email from the docketing mailbox drop.
Schedule::command('mail:ingest')->everyFifteenMinutes();

// The morning docket: due tasks and renewals per user.
Schedule::command('reminders:digest')->weekdays()->dailyAt('07:00');
