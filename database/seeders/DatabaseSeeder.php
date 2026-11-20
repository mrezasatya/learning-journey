<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data DUMMY untuk prototipe (Brief 9.6). Nama, NIK, dan struktur organisasi bersifat fiktif.
 * Jalankan: php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Saat seeding, penugasan massal tidak perlu memicu ratusan notifikasi/email
        config(['blj.mute_notifications' => true, 'blj.mute_email' => true]);

        $this->call([
            RoleSeeder::class,
            MasterDataSeeder::class,
            EmployeeSeeder::class,
            SurveyTemplateSeeder::class,
            ProgramSeeder::class,
            DemoMediaSeeder::class,      // Tahap 4
            AssignmentSeeder::class,     // Tahap 5
            LearningSimulationSeeder::class, // Tahap 6
        ]);

        // Jalankan proses harian sekali (in-app saja) agar ada notifikasi & status overdue untuk demo
        config(['blj.mute_notifications' => false]);
        app(\App\Services\ReminderService::class)->runDaily();
    }
}
