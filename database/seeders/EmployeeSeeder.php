<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkLocation;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ±70 karyawan fiktif dengan hierarki atasan. Akun demo (password: Bukaka123):
 *   superadmin  — Super Admin (akun sistem)
 *   100001      — Admin L&D
 *   100010      — Atasan (Dept. Head Produksi)
 *   100101      — Karyawan (Operator Produksi)
 *   100102      — Karyawan baru, wajib ganti password saat login pertama
 */
class EmployeeSeeder extends Seeder
{
    private int $seq = 100200;

    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $faker->seed(2026);
        $hash = Hash::make('Bukaka123');

        $pos = Position::pluck('id', 'code');
        $grade = Grade::pluck('id', 'code');
        $dept = Department::pluck('id', 'code');
        $loc = WorkLocation::pluck('id', 'code');

        $make = function (string $nik, string $name, string $posCode, string $gradeCode, string $deptCode, string $locCode, ?string $sup, string $status = 'TETAP', ?string $join = null) use ($pos, $grade, $dept, $loc, $faker) {
            return Employee::updateOrCreate(['nik' => $nik], [
                'full_name' => $name,
                'email' => strtolower(str_replace([' ', '.', ','], ['.', '', ''], $name)).'@contoh.co.id',
                'phone' => '08'.$faker->numerify('##########'),
                'position_id' => $pos[$posCode],
                'grade_id' => $grade[$gradeCode],
                'department_id' => $dept[$deptCode],
                'work_location_id' => $loc[$locCode],
                'supervisor_nik' => $sup,
                'employment_status' => $status,
                'join_date' => $join ?? $faker->dateTimeBetween('-12 years', '-1 years')->format('Y-m-d'),
                'is_active' => true,
            ]);
        };

        // --- Pimpinan (Division Head & Dept. Head) ---
        $make('100900', 'Hendra Wijaya', 'DIRHEAD', '7A', 'HC', 'HO', null);
        $make('100901', 'Bambang Santoso', 'DIRHEAD', '7A', 'PROD', 'PLT-01', null);
        $make('100902', 'Rudi Hartono', 'DIRHEAD', '7A', 'PRJ', 'HO', null);
        $make('100002', 'Siti Rahmawati', 'DEPTHEAD', '6A', 'HC', 'HO', '100900');
        $make('100010', 'Agus Prasetyo', 'DEPTHEAD', '6A', 'PROD', 'PLT-01', '100901');
        $make('100011', 'Dewi Lestari', 'DEPTHEAD', '6A', 'QA', 'PLT-01', '100901');
        $make('100012', 'Yusuf Maulana', 'DEPTHEAD', '6B', 'HSE', 'PLT-01', '100901');
        $make('100020', 'Fajar Nugroho', 'DEPTHEAD', '6A', 'CONS', 'SITE-KAL', '100902');
        $make('100021', 'Rina Marlina', 'DEPTHEAD', '6B', 'PRJ', 'HO', '100902');

        // --- Akun demo ---
        $make('100001', 'Maya Anggraini', 'LDOFF', '4A', 'HC', 'HO', '100002');
        $make('100101', 'Budi Setiawan', 'OPR', '2A', 'PROD', 'PLT-01', '100010');
        $make('100102', 'Andi Saputra', 'OPR', '1B', 'PROD', 'PLT-01', '100010', 'KONTRAK', now()->subDays(10)->format('Y-m-d'));

        // --- Karyawan lain (acak, deterministik) ---
        $teams = [
            ['100010', 'PROD', 'PLT-01', ['OPR' => 9, 'WELD' => 5, 'SPV' => 2]],
            ['100011', 'QA', 'PLT-01', ['QC' => 6, 'ENG' => 2]],
            ['100012', 'HSE', 'PLT-01', ['HSEOFF' => 4]],
            ['100020', 'CONS', 'SITE-KAL', ['OPR' => 6, 'WELD' => 4, 'SPV' => 2, 'HSEOFF' => 1]],
            ['100021', 'PRJ', 'SITE-SUL', ['ENG' => 4, 'OPR' => 5, 'STAF-ADM' => 1]],
            ['100002', 'HC', 'HO', ['STAF-ADM' => 3, 'LDOFF' => 1]],
        ];
        $gradeFor = ['OPR' => ['1A', '1B', '2A'], 'WELD' => ['2A', '2B', '3A'], 'SPV' => ['4A', '4B'], 'QC' => ['2B', '3A'],
            'ENG' => ['3B', '4A'], 'HSEOFF' => ['3A', '3B'], 'STAF-ADM' => ['2A', '2B'], 'LDOFF' => ['3B']];
        foreach ($teams as [$sup, $d, $l, $roles]) {
            foreach ($roles as $p => $n) {
                for ($k = 0; $k < $n; $k++) {
                    $status = $l === 'PLT-01' || $l === 'HO' ? $faker->randomElement(['TETAP', 'TETAP', 'KONTRAK']) : $faker->randomElement(['PROYEK', 'KONTRAK', 'TETAP']);
                    $make((string) ++$this->seq, $faker->name(), $p, $faker->randomElement($gradeFor[$p]), $d, $l, $sup, $status);
                }
            }
        }

        // Satu karyawan nonaktif (resign) — untuk menguji BR-05
        $make('100199', 'Joko Susilo', 'OPR', '1A', 'PROD', 'PLT-01', '100010', 'KONTRAK')->update(['is_active' => false, 'end_date' => now()->subMonth()]);

        // --- Akun login untuk semua karyawan ---
        foreach (Employee::all() as $e) {
            User::updateOrCreate(['username' => $e->nik], [
                'employee_id' => $e->id,
                'name' => $e->full_name,
                'password' => $hash,
                'must_change_password' => $e->nik === '100102',
            ]);
        }
        User::updateOrCreate(['username' => 'superadmin'], [
            'name' => 'Super Admin', 'password' => $hash, 'must_change_password' => false,
        ]);

        $super = Role::where('code', Role::SUPER_ADMIN)->first();
        $admin = Role::where('code', Role::ADMIN_LD)->first();
        User::where('username', 'superadmin')->first()->roles()->syncWithoutDetaching([$super->id]);
        User::where('username', '100001')->first()->roles()->syncWithoutDetaching([$admin->id]);
    }
}
