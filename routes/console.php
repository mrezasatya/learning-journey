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
