<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Program;
use App\Models\SessionParticipant;
use App\Models\TrainingSession;
use Illuminate\Support\Str;

/** Sesi tatap muka/klasikal & absensi (BR-42 s.d. BR-45, US-23). */
class SessionService
{
    public function __construct(private LearningFlow $flow) {}

    public function create(array $data): TrainingSession
    {
        $s = TrainingSession::create($data + ['qr_token' => Str::random(40)]);
        AuditLogger::log('session.create', $s, null, $s->toArray());

        return $s;
    }

    /** @return array{added:int, full:int} */
    public function invite(TrainingSession $s, array $employeeIds): array
    {
        $added = $full = 0;
        foreach (array_unique($employeeIds) as $id) {
            if ($s->participants()->where('employee_id', $id)->exists()) {
                continue;
            }
            if ($s->participants()->count() >= $s->capacity) {
                $full++;
                continue;
            }
            $s->participants()->create(['employee_id' => $id]);
            $added++;
        }
        AuditLogger::log('session.invite', $s, null, ['employee_ids' => $employeeIds, 'added' => $added]);

        return compact('added', 'full');
    }

    /** QR check-in oleh karyawan (berlaku ±30 menit dari jadwal — US-23 AC1). */
    public function checkin(TrainingSession $s, Employee $e): string
    {
        if (! $s->isCheckinOpen()) {
            return 'QR sesi hanya berlaku pada waktu sesi ± '.config('blj.defaults.qr_session_tolerance_minutes').' menit.';
        }
        $p = $s->participants()->where('employee_id', $e->id)->first();
        if (! $p) {
            // Karyawan yang ditugaskan program ini boleh check-in walau belum diundang (selama kapasitas ada)
            $assigned = Assignment::where('employee_id', $e->id)->whereIn('program_id', Program::where('code', $s->program->code)->pluck('id'))->exists();
            if (! $assigned) {
                return 'Anda tidak terdaftar pada sesi atau program ini.';
            }
            if ($s->participants()->count() >= $s->capacity) {
                return 'Kapasitas sesi penuh. Hubungi panitia.';
            }
            $p = $s->participants()->create(['employee_id' => $e->id]);
        }
        if ($p->attendance_status !== 'PRESENT') {
            $p->update(['attendance_status' => 'PRESENT', 'checkin_at' => now(), 'checkin_method' => 'QR']);
            $this->afterAttendance($s, $e);
        }

        return 'OK';
    }

    /** Koreksi/absensi manual oleh admin — alasan wajib untuk koreksi (US-23 AC2). */
    public function mark(SessionParticipant $p, string $status, ?string $reason, ?int $score = null): void
    {
        $before = $p->only(['attendance_status', 'offline_score']);
        $p->update([
            'attendance_status' => $status,
            'checkin_at' => $status === 'PRESENT' ? ($p->checkin_at ?? now()) : null,
            'checkin_method' => $status === 'PRESENT' ? ($p->checkin_method ?? 'MANUAL') : null,
            'correction_reason' => $reason,
            'offline_score' => $score ?? $p->offline_score,
        ]);
        AuditLogger::log('session.attendance', $p, $before, $p->only(['attendance_status', 'offline_score', 'correction_reason']));
        if ($status === 'PRESENT') {
            $this->afterAttendance($p->session, $p->employee);
        }
    }

    /** Kehadiran dapat melengkapi syarat program blended (BR-45). */
    private function afterAttendance(TrainingSession $s, Employee $e): void
    {
        $assignments = Assignment::where('employee_id', $e->id)->whereIn('program_id', Program::where('code', $s->program->code)->pluck('id'))
            ->whereIn('status', Assignment::OPEN_STATUSES)->get();
        foreach ($assignments as $a) {
            $this->flow->checkCompletion($a);
        }
    }
}
