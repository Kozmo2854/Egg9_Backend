<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule weekly cycle processing to run every Monday at 00:01
Schedule::command('egg9:process-weekly-cycle')
    ->weekly()
    ->mondays()
    ->at('00:01')
    ->timezone('UTC');
