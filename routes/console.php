<?php

use App\Services\AssignmentEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Perintah & jadwal otomatis BLJ (dijalankan oleh `php artisan schedule:work`)
|--------------------------------------------------------------------------
*/

// US-13: penugasan ulang Wajib Tahunan setiap 1 Januari
Artisan::command('blj:assign-annual {year?}', function (AssignmentEngine $engine) {
    $year = (int) ($this->argument('year') ?: now()->year);
    $n = $engine->assignAnnual($year);
    $this->info("Wajib Tahunan periode {$year}: {$n} penugasan baru.");
})->purpose('Menugaskan ulang program Wajib Tahunan untuk periode (tahun) tertentu');

Schedule::command('blj:assign-annual')->yearlyOn(1, 1, '00:10');

// BR-37–BR-40: status overdue, pengingat H-90…H-1, eskalasi atasan, rekomendasi teguran
Artisan::command('blj:daily', function (\App\Services\ReminderService $reminders) {
    $r = $reminders->runDaily();
    $this->info("Overdue baru: {$r['overdue']} · Pengingat terkirim: {$r['reminders']}");
})->purpose('Proses harian: tandai overdue, kirim pengingat & eskalasi');

Schedule::command('blj:daily')->dailyAt('06:00');
