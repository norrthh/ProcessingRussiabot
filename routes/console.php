<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Проверка истекших заявок каждую минуту (резервный вариант, если queue worker не работает)
Schedule::command('join-requests:check-expired')->everyMinute();
