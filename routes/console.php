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

// Instalasi awal di server (TANPA data dummy): peran, template survei default, akun Super Admin
Artisan::command('blj:install {username=superadmin}', function () {
    $this->call('db:seed', ['--class' => \Database\Seeders\RoleSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => \Database\Seeders\SurveyTemplateSeeder::class, '--force' => true]);
    $username = $this->argument('username');
    if (\App\Models\User::where('username', $username)->exists()) {
        $this->warn("Akun {$username} sudah ada — tidak diubah.");

        return;
    }
    $password = \Illuminate\Support\Str::password(16, symbols: false);
    $user = \App\Models\User::create(['username' => $username, 'name' => 'Super Admin', 'password' => $password, 'must_change_password' => true]);
    $user->roles()->attach(\App\Models\Role::where('code', \App\Models\Role::SUPER_ADMIN)->value('id'));
    $this->info("Akun {$username} dibuat. Password sementara (catat sekarang, wajib diganti saat login): {$password}");
})->purpose('Instalasi awal BLJ di server produksi tanpa data dummy');
