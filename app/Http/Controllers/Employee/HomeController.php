<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Services\LearningFlow;
use Illuminate\Http\Request;

/** Beranda karyawan: training wajib + tenggat + progres (US-15). */
class HomeController extends Controller
{
    public function __invoke(Request $request, LearningFlow $flow)
    {
        $employee = $request->user()->employee;
        $all = Assignment::with(['program.preAssessment', 'program.postAssessment', 'surveyResponse', 'certificate'])
            ->where('employee_id', $employee->id)->get();

        $open = $all->filter->isOpen()->sortBy('due_at')->values(); // AC1: tenggat terdekat di atas
        $thisYear = $all->filter(fn ($a) => $a->period_year === 0 ? $a->assigned_at->year === now()->year || $a->isOpen() : $a->period_year === now()->year)
            ->reject(fn ($a) => $a->status === Assignment::EXEMPTED);
        $recentDone = $all->filter->isDone()->sortByDesc('completed_at')->take(3)->values();

        $cards = $open->map(fn ($a) => ['a' => $a, 'percent' => $flow->percent($a), 'resume' => $flow->resumeUrl($a), 'step' => $flow->currentStep($a)]);

        return view('employee.home', [
            'employee' => $employee,
            'cards' => $cards,
            'recentDone' => $recentDone,
            'doneCount' => $thisYear->filter->isDone()->count(),
            'totalCount' => $thisYear->count(),
            'nearest' => $open->first(),
            'overdueCount' => $open->where('status', Assignment::OVERDUE)->count(),
        ]);
    }
}
