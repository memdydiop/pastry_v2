<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:database --keep=30')
    ->daily()
    ->at('02:00')
    ->environments(['production'])
    ->description('Daily database backup (retention: 30 days)');
