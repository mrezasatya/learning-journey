<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\SurveyResponse;
use Illuminate\Support\Collection;

/**
 * Angka kepatuhan & indikator keberhasilan (Brief 1.3, US-25).
 * Populasi = penugasan karyawan aktif pada periode yang dipilih, tidak termasuk yang dikecualikan.
 */
class ComplianceReport
{
    /** Penugasan untuk satu periode: Wajib Tahunan periode itu + Onboarding/Sekali yang ditugaskan pada tahun itu. */
    public function rows(int $year, ?int $programId = null): Collection
    {
        return Assignment::with(['employee.department.division', 'employee.workLocation', 'program'])
            ->whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->where('status', '!=', Assignment::EXEMPTED)
            ->where(fn ($q) => $q->where('period_year', $year)
                ->orWhere(fn ($w) => $w->where('period_year', 0)->whereYear('assigned_at', $year)))
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->get();
    }

    public function summary(Collection $rows): array
    {
        $total = $rows->count();
        $done = $rows->filter->isDone()->count();
        $onTime = $rows->where('status', Assignment::COMPLETED)->count();

        return [
            'total' => $total,
            'done' => $done,
            'rate' => $total ? round($done / $total * 100, 1) : 0,
            'on_time_rate' => $total ? round($onTime / $total * 100, 1) : 0,
            'overdue' => $rows->where('status', Assignment::OVERDUE)->count(),
            'overdue_rate' => $total ? round($rows->where('status', Assignment::OVERDUE)->count() / $total * 100, 1) : 0,
            'due30' => $rows->filter(fn ($a) => in_array($a->status, [Assignment::NOT_STARTED, Assignment::IN_PROGRESS], true) && $a->due_at->lte(now()->addDays(30)))->count(),
            'not_started' => $rows->where('status', Assignment::NOT_STARTED)->count(),
            'in_progress' => $rows->where('status', Assignment::IN_PROGRESS)->count(),
        ];
    }

    /** Completion rate per dimensi (program/departemen/divisi/lokasi/tipe lokasi). */
    public function breakdown(Collection $rows, string $by): Collection
    {
        $key = match ($by) {
            'program' => fn ($a) => [$a->program_id, $a->program->code, 'program_id'],
            'department' => fn ($a) => [$a->employee->department_id, $a->employee->department?->name ?? '—', 'department_id'],
            'division' => fn ($a) => [$a->employee->department?->division_id, $a->employee->department?->division?->name ?? '—', 'division_id'],
            'location' => fn ($a) => [$a->employee->work_location_id, $a->employee->workLocation?->name ?? '—', 'work_location_id'],
            'location_type' => fn ($a) => [$a->employee->workLocation?->type, \App\Models\WorkLocation::TYPES[$a->employee->workLocation?->type] ?? '—', 'location_type'],
        };

        return $rows->groupBy(fn ($a) => $key($a)[0])->map(function ($g) use ($key) {
            [$id, $label, $param] = $key($g->first());
            $done = $g->filter->isDone()->count();

            return ['id' => $id, 'label' => $label, 'param' => $param, 'total' => $g->count(), 'done' => $done,
                'overdue' => $g->where('status', Assignment::OVERDUE)->count(),
                'rate' => round($done / max(1, $g->count()) * 100, 1)];
        })->sortBy('rate')->values();
    }

    private function pct(?float $v): string
    {
        return $v === null ? '—' : str_replace('.', ',', (string) round($v, 1)).'%';
    }

    /** Indikator keberhasilan Brief 1.3 vs target. */
    public function indicators(Collection $rows, int $year): array
    {
        $s = $this->summary($rows);
        $ids = $rows->pluck('id');
        $postFirst = \App\Models\AssessmentAttempt::whereIn('assignment_id', $ids)->where('attempt_no', 1)->where('cycle_no', 1)
            ->whereNotNull('submitted_at')->whereHas('assessment', fn ($q) => $q->where('kind', 'POST'))->get();
        $firstPass = $postFirst->count() ? round($postFirst->where('passed', true)->count() / $postFirst->count() * 100, 1) : null;
        $likert = SurveyResponse::whereIn('assignment_id', $ids)->get()->flatMap(fn ($r) => collect($r->answers)->filter(fn ($v) => is_numeric($v)));
        $site = $rows->filter(fn ($a) => $a->employee->workLocation?->type === 'SITE');
        $siteDone = $site->filter->isDone();

        return [
            ['Completion rate training wajib sebelum tenggat', $this->pct($s['on_time_rate']), '≥ 95%', $s['on_time_rate'] >= 95],
            ['Proporsi penugasan overdue', $this->pct($s['overdue_rate']), '≤ 5%', $s['overdue_rate'] <= 5],
            ['Kelulusan post-test pada percobaan pertama', $this->pct($firstPass), '≥ 80%', $firstPass !== null && $firstPass >= 80],
            ['Rata-rata skor survei reaksi (1–5)', $likert->count() ? number_format($likert->avg(), 2, ',', '.') : '—', '≥ 4,0', $likert->count() && $likert->avg() >= 4],
            ['Penyelesaian online oleh karyawan site', $site->count() ? $this->pct($siteDone->where('completion_method', 'ONLINE')->count() / $site->count() * 100) : '—', 'Baseline', null],
        ];
    }
}
