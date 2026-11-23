<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Program;
use App\Services\LearningFlow;
use App\Services\SessionService;
use Illuminate\Database\Seeder;

/** Sesi tatap muka contoh untuk program blended K3-02 (Tahap 9). */
class SessionSeeder extends Seeder
{
    public function run(SessionService $sessions, LearningFlow $flow): void
    {
        $program = Program::where('code', 'K3-02')->where('status', 'PUBLISHED')->first();
        if (! $program) {
            return;
        }
        $assignees = Assignment::with('employee.workLocation')->where('program_id', $program->id)->where('period_year', now()->year)->get();

        // Sesi lalu di Plant: sebagian besar hadir
        $past = $sessions->create(['program_id' => $program->id, 'title' => 'Praktik APAR – Batch Plant', 'location' => 'Lapangan Apel Plant Utama',
            'start_at' => now()->subDays(20)->setTime(8, 0), 'end_at' => now()->subDays(20)->setTime(11, 0), 'capacity' => 40]);
        $plant = $assignees->filter(fn ($a) => $a->employee->workLocation?->type === 'PLANT')->take(30);
        $sessions->invite($past, $plant->pluck('employee_id')->all());
        foreach ($past->participants()->with('employee')->get() as $i => $p) {
            $present = $i % 6 !== 0;
            $p->update(['attendance_status' => $present ? 'PRESENT' : 'ABSENT', 'checkin_at' => $present ? $past->start_at->copy()->addMinutes(5) : null,
                'checkin_method' => $present ? 'QR' : null, 'offline_score' => $present ? [80, 85, 90, 95, 100][$i % 5] : null]);
        }
        // Lengkapi status program bagi yang sudah memenuhi syarat online + hadir
        foreach ($plant as $a) {
            $flow->checkCompletion($a);
        }

        // Sesi hari ini (untuk mencoba QR check-in) dan sesi mendatang di site
        $today = $sessions->create(['program_id' => $program->id, 'title' => 'Praktik APAR – Demo QR (hari ini)', 'location' => 'Ruang Training HO',
            'start_at' => now()->subMinutes(30), 'end_at' => now()->addHours(8), 'capacity' => 30]);
        $sessions->invite($today, $assignees->filter(fn ($a) => $a->employee->nik === '100101')->pluck('employee_id')->all());

        $site = $sessions->create(['program_id' => $program->id, 'title' => 'Praktik APAR – Site Kalimantan', 'location' => 'Site Proyek Kalimantan, area muster point',
            'start_at' => now()->addDays(10)->setTime(7, 30), 'end_at' => now()->addDays(10)->setTime(10, 30), 'capacity' => 25]);
        $sessions->invite($site, $assignees->filter(fn ($a) => $a->employee->workLocation?->code === 'SITE-KAL')->pluck('employee_id')->all());
    }
}
