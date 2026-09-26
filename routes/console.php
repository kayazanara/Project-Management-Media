<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================
// SCHEDULER: Reminder Deadline
// ============================================
// Command ini akan jalan otomatis jam 08:00 setiap hari
// (asalkan cron `php artisan schedule:run` sudah setup di VPS — lihat catatan bawah)
Schedule::command('reminders:send')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->description('Kirim reminder H-3, H-1, dan Overdue ke anggota project');