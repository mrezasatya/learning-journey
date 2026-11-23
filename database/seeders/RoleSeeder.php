<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            Role::SUPER_ADMIN => 'Super Admin',
            Role::ADMIN_LD => 'Admin L&D',
            Role::SUPERVISOR => 'Atasan',
            Role::EMPLOYEE => 'Karyawan',
        ] as $code => $name) {
            Role::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
