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
        $this->call([
            RoleSeeder::class,
            MasterDataSeeder::class,
            EmployeeSeeder::class,
            SurveyTemplateSeeder::class,
            ProgramSeeder::class,
            DemoMediaSeeder::class,      // Tahap 4
            AssignmentSeeder::class,     // Tahap 5
        ]);
    }
}
