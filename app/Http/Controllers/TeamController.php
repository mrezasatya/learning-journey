<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Program;
use App\Services\LearningFlow;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;

/** Menu "Tim Saya" untuk Atasan (US-21, BR-48). */
class TeamController extends Controller
{
    public function index(Request $request, LearningFlow $flow)
    {
        [$members, $scope] = $this->members($request);
        $assignments = $this->assignments($request, $members->pluck('id'))->get();
        $all = Assignment::whereIn('employee_id', $members->pluck('id'))->where('period_year', '!=', now()->year - 1)
            ->where(fn ($q) => $q->where('period_year', now()->year)->orWhere(fn ($w) => $w->where('period_year', 0)->where('assigned_at', '>=', now()->startOfYear())->orWhereIn('status', Assignment::OPEN_STATUSES)))
            ->where('status', '!=', Assignment::EXEMPTED)->get();

        return view('team.index', [
            'members' => $members, 'scope' => $scope,
            'rows' => $assignments->map(fn ($a) => ['a' => $a, 'percent' => $flow->percent($a)]),
            'summary' => [
                'members' => $members->count(),
                'rate' => $all->count() ? round($all->filter->isDone()->count() / $all->count() * 100) : 0,
                'overdue' => $all->where('status', Assignment::OVERDUE)->count(),
                'due30' => $all->filter(fn ($a) => in_array($a->status, [Assignment::NOT_STARTED, Assignment::IN_PROGRESS]) && $a->due_at->lte(now()->addDays(30)))->count(),
            ],
            'programs' => Program::whereIn('id', Assignment::whereIn('employee_id', $members->pluck('id'))->distinct()->pluck('program_id'))->orderBy('code')->get(),
            'canIndirect' => $request->user()->employee->allSubordinateNiks(2) !== $members->pluck('nik')->all(),
        ]);
    }

    public function export(Request $request, LearningFlow $flow)
    {
        [$members] = $this->members($request);
        $rows = $this->assignments($request, $members->pluck('id'))->get()->map(fn ($a) => [
            $a->employee->nik, $a->employee->full_name, $a->employee->position?->name, $a->program->code, $a->program->name,
            $a->periodLabel(), $a->statusLabel(), $flow->percent($a), $a->due_at->format('Y-m-d'), $a->final_score,
        ]);

        return Spreadsheet::download('tim-saya-'.now()->format('Ymd').'.xlsx', ['Tim' => [
            ['NIK', 'Nama', 'Jabatan', 'Kode', 'Program', 'Periode', 'Status', 'Progres %', 'Tenggat', 'Nilai post-test'], $rows]]);
    }

    /** Anggota tim langsung; Dept./Division Head dapat melihat tim tidak langsung (Q-05). */
    private function members(Request $request): array
    {
        $me = $request->user()->employee;
        $scope = $request->query('lingkup') === 'semua' ? 'semua' : 'langsung';
        $niks = $scope === 'semua' ? $me->allSubordinateNiks() : $me->subordinates()->where('is_active', true)->pluck('nik')->all();

        return [Employee::with('position')->whereIn('nik', $niks)->where('is_active', true)->orderBy('full_name')->get(), $scope];
    }

    private function assignments(Request $request, $employeeIds)
    {
        return Assignment::with(['employee.position', 'program.preAssessment', 'program.postAssessment', 'surveyResponse'])
            ->whereIn('employee_id', $employeeIds)
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('employee', fn ($e) => $e->where('nik', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%")))
            ->when($request->query('program_id'), fn ($q, $v) => $q->where('program_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $v === 'open' ? $q->open() : ($v === 'done' ? $q->done() : $q->where('status', $v)))
            ->when(! $request->query('periode_lalu'), fn ($q) => $q->where(fn ($w) => $w->where('period_year', now()->year)->orWhere('period_year', 0)->orWhereIn('status', Assignment::OPEN_STATUSES)))
            ->orderByRaw("CASE status WHEN 'OVERDUE' THEN 0 WHEN 'NOT_STARTED' THEN 1 WHEN 'IN_PROGRESS' THEN 2 ELSE 3 END")
            ->orderBy('due_at');
    }
}
