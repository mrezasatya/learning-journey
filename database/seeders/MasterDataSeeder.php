<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\Grade;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            'DIV-HC' => ['Human Capital & GA', ['HC' => 'Human Capital', 'GA' => 'General Affairs']],
            'DIV-OPS' => ['Operasi & Manufaktur', ['PROD' => 'Produksi', 'QA' => 'Quality Assurance', 'HSE' => 'HSE']],
            'DIV-PRJ' => ['Proyek & Konstruksi', ['PRJ' => 'Project Management', 'CONS' => 'Konstruksi Lapangan']],
            'DIV-FIN' => ['Keuangan', ['FIN' => 'Finance & Accounting']],
        ];
        foreach ($divisions as $code => [$name, $depts]) {
            $div = Division::updateOrCreate(['code' => $code], ['name' => $name]);
            foreach ($depts as $dcode => $dname) {
                Department::updateOrCreate(['code' => $dcode], ['name' => $dname, 'division_id' => $div->id]);
            }
        }

        foreach ([
            ['DIRHEAD', 'Division Head', 'Division Head'],
            ['DEPTHEAD', 'Department Head', 'Dept. Head'],
            ['SECTHEAD', 'Section Head', 'Section Head'],
            ['SPV', 'Supervisor', 'Senior Staf'],
            ['ENG', 'Engineer', 'Senior Staf'],
            ['STAF-ADM', 'Staf Administrasi', 'Staf'],
            ['OPR', 'Operator Produksi', 'Staf'],
            ['WELD', 'Welder', 'Staf'],
            ['QC', 'QC Inspector', 'Staf'],
            ['HSEOFF', 'HSE Officer', 'Staf'],
            ['LDOFF', 'L&D Officer', 'Staf'],
        ] as [$code, $name, $level]) {
            Position::updateOrCreate(['code' => $code], ['name' => $name, 'level' => $level]);
        }

        $i = 0;
        foreach (range(1, 7) as $n) {
            foreach (['A', 'B'] as $l) {
                Grade::updateOrCreate(['code' => $n.$l], ['sort_order' => ++$i]);
            }
        }

        foreach ([
            ['HO', 'Head Office', 'HO', 'Jawa Barat'],
            ['PLT-01', 'Plant Utama', 'PLANT', 'Jawa Barat'],
            ['SITE-KAL', 'Site Proyek Kalimantan', 'SITE', 'Kalimantan'],
            ['SITE-SUL', 'Site Proyek Sulawesi', 'SITE', 'Sulawesi'],
        ] as [$code, $name, $type, $region]) {
            WorkLocation::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'region' => $region]);
        }
    }
}
