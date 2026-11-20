<?php

namespace Database\Seeders;

use App\Models\AssignmentRule;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Program;
use App\Models\User;
use App\Services\AssignmentEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Aturan penugasan contoh (Tahap 5). Penugasan dibuat "mundur" seolah aturan sudah aktif sejak
 * 1 Januari tahun berjalan, sehingga demo memperlihatkan tenggat yang realistis.
 */
class AssignmentSeeder extends Seeder
{
    public function run(AssignmentEngine $engine): void
    {
        $admin = User::where('username', '100001')->first();
        $yearStart = Carbon::create(now()->year, 1, 1);
        $p = fn ($code) => Program::where('code', $code)->where('status', 'PUBLISHED')->first();
        $positions = Position::whereIn('code', ['OPR', 'WELD', 'SPV', 'HSEOFF'])->pluck('id')->map(fn ($i) => (string) $i)->all();

        $rules = [
            ['ONB-01', 'Onboarding seluruh karyawan baru', ['logic' => 'AND', 'items' => []]],
            ['K3-01', 'Induksi K3 untuk karyawan plant & site', ['logic' => 'AND', 'items' => [['field' => 'location_type', 'op' => 'in', 'values' => ['PLANT', 'SITE']]]]],
            ['ISO-01', 'ISO 9001 untuk seluruh karyawan', ['logic' => 'AND', 'items' => []]],
            ['K3-02', 'Praktik APAR untuk operator, welder, supervisor & HSE', ['logic' => 'AND', 'items' => [['field' => 'position_id', 'op' => 'in', 'values' => $positions]]]],
        ];

        foreach ($rules as [$code, $name, $conditions]) {
            $program = $p($code);
            if (! $program) {
                continue;
            }
            $rule = AssignmentRule::firstOrCreate(['program_id' => $program->id, 'name' => $name], [
                'conditions' => $conditions, 'effective_from' => $yearStart, 'is_active' => true, 'created_by' => $admin?->id,
            ]);
            foreach ($engine->matchingEmployees($conditions, $program, $yearStart) as $e) {
                /** @var Employee $e */
                $at = $e->join_date->gt($yearStart) ? $e->join_date->copy() : $yearStart->copy();
                $engine->assign($e, $program, 'RULE', rule: $rule, at: $at);
            }
        }
    }
}
