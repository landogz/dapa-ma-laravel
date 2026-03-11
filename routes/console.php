<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| DAPE-MA Scheduled Tasks
|--------------------------------------------------------------------------
| Ensure your server runs: * * * * * php /path/to/artisan schedule:run
|--------------------------------------------------------------------------
*/
Schedule::command('dape-ma:publish-scheduled-posts')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
