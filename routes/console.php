<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Cek deadline project & kirim reminder H-3, H-1, Overdue setiap hari jam 08:00 WIB
Schedule::command('notify:deadlines')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(10)   // Cegah tumpang tindih, lock 10 menit
    ->onOneServer();           // Hanya 1 server yang jalankan (untuk multi-server)