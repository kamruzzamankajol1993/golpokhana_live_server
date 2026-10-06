<?php

use App\Services\Hr\TipsoiAttendanceAutoSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Server-side fallback: when cPanel/server cron runs `php artisan schedule:run`
// every five minutes, TIPSOI attendance remains synced even if no browser is open.
Schedule::call(function (): void {
    app(TipsoiAttendanceAutoSyncService::class)->syncThroughToday(
        forceCurrentMonth: false,
        minimumIntervalSeconds: 240,
    );
})
    ->name('karachi-tipsoi-attendance-auto-sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
