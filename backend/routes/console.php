<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('marketing:send-abandoned-cart-reminders')->hourly();
Schedule::command('marketing:send-review-reminders')->dailyAt('10:00');
