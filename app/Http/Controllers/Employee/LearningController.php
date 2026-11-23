<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Services\AssessmentService;
use App\Services\LearningFlow;
use Illuminate\Http\Request;

/** Detail program & pemutar materi (US-16, BR-18–BR-20). */
class LearningController extends Controller
{
    use Concerns;

    public function __construct(private LearningFlow $flow) {}

    public function program(Assignment $assignment, AssessmentService $tests)
    {
        $a = $this->own($assignment);

        return view('employee.program', [
            'a' => $a,
            'lessons' => $this->flow->lessons($a),
            'step' => $this->flow->currentStep($a),
            'percent' => $this->flow->percent($a),
            'preDone' => $this->flow->preTestDone($a),
            'postPassed' => $this->flow->postPassed($a),
            'postStatus' => $tests->postStatus($a),
            'sessionAttended' => $this->flow->sessionAttended($a),
            'resume' => $this->flow->resumeUrl($a),
            'sessions' => $a->program->is_blended ? \App\Models\TrainingSession::whereIn('program_id', \App\Models\Program::where('code', $a->program->code)->pluck('id'))
                ->whereHas('participants', fn ($q) => $q->where('employee_id', $a->employee_id))->orderBy('start_at')->get() : collect(),
        ]);
    }

    public function lesson(Assignment $assignment, Lesson $lesson)
    {
        $a = $this->own($assignment);
        $list = $this->flow->lessons($a);
        $idx = $list->search(fn ($x) => $x['lesson']->id === $lesson->id);
        abort_if($idx === false, 404);
        $item = $list[$idx];
        if ($item['locked'] && $a->isOpen()) {
            return redirect()->route('learn.program', $a)->withErrors([
                $this->flow->preTestDone($a) ? 'Selesaikan materi sebelumnya terlebih dahulu (materi dibuka berurutan).' : 'Kerjakan pre-test terlebih dahulu.',
            ]);
        }
        if ($a->isOpen()) {
            $this->flow->markStarted($a);
        }
        $progress = $a->isOpen() ? $this->flow->progressFor($a, $lesson) : $item['progress'];
        if ($a->isOpen() && in_array($lesson->type, ['TEXT', 'IMAGE'], true) && ! $progress->completed_at) {
            $progress->touch();
        }

        return view('employee.lesson', [
            'a' => $a, 'lesson' => $lesson->load('file'), 'progress' => $progress, 'tracking' => $a->isOpen(),
            'list' => $list, 'idx' => $idx,
            'prev' => $list[$idx - 1] ?? null, 'next' => $list[$idx + 1] ?? null,
        ]);
    }

    /** Heartbeat progres (video tiap ≤ 10 detik, dokumen per halaman, teks "tandai selesai"). */
    public function progress(Request $request, Assignment $assignment, Lesson $lesson)
    {
        $a = $this->own($assignment);
        abort_unless($a->isOpen(), 409, 'Penugasan tidak aktif.');
        $item = $this->flow->lessons($a)->first(fn ($x) => $x['lesson']->id === $lesson->id);
        abort_if(! $item || $item['locked'], 403);

        $result = match ($lesson->type) {
            'VIDEO' => (function () use ($request, $a, $lesson) {
                $p = $this->flow->recordVideo($a, $lesson, (float) $request->input('position', 0));

                return ['ok' => true, 'max_position' => $p->max_position_seconds, 'completed' => (bool) $p->completed_at];
            })(),
            'PDF', 'SLIDE' => $this->flow->recordPage($a, $lesson, (int) $request->input('page'), (string) $request->input('action', 'done')),
            default => $this->flow->completeTimed($a, $lesson) + ['completed' => true],
        };
        if (($result['completed'] ?? false) && $result['ok']) {
            $result['next_url'] = $this->flow->resumeUrl($a->fresh());
        }

        return response()->json($result);
    }
}
