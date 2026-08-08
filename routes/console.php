<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Published exchange rates, once a morning before trading starts. Nothing else
 * in the application calls the rate service — see SyncExchangeRatesAction.
 *
 * A missed run is not a problem: every lookup takes the newest rate on or before
 * the date it needs, so yesterday's figure carries over until this succeeds.
 */
Schedule::command('currency:sync')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();
