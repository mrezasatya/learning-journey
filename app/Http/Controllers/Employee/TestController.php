<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Services\AssessmentService;
use App\Services\LearningFlow;
use Illuminate\Http\Request;

/** Pre-test & post-test dengan timer berbasis server (US-17). */
class TestController extends Controller
{
    use Concerns;

    public function __construct(private AssessmentService $tests, private LearningFlow $flow) {}

    public function intro(Assignment $assignment, string $kind)
    {
        $a = $this->own($assignment);
        $test = $this->test($a, $kind);

        return view('employee.test-intro', [
            'a' => $a, 'test' => $test, 'kind' => $kind,
            'block' => $this->tests->openAttempt($a, $test) ? null : $this->tests->blockReason($a, $test),
            'open' => $this->tests->openAttempt($a, $test),
            'postStatus' => $kind === 'POST' ? $this->tests->postStatus($a) : null,
            'history' => $a->attempts()->where('assessment_id', $test->id)->whereNotNull('submitted_at')->orderByDesc('id')->get(),
        ]);
    }

    public function start(Assignment $assignment, string $kind)
    {
        $a = $this->own($assignment);
        try {
            $attempt = $this->tests->startOrResume($a, $this->test($a, $kind));
        } catch (\DomainException $e) {
            return redirect()->route('learn.test.intro', [$a, $kind])->withErrors([$e->getMessage()]);
        }

        return redirect()->route('learn.test.take', [$a, $attempt]);
    }

    public function take(Assignment $assignment, AssessmentAttempt $attempt)
    {
        $a = $this->own($assignment);
        abort_unless($attempt->assignment_id === $a->id, 404);
        if (! $attempt->isOpen()) {
            return redirect()->route('learn.test.result', [$a, $attempt]);
        }
        if ($attempt->deadline_at->isPast()) { // waktu habis saat terputus → nilai otomatis
            $this->tests->submit($attempt);

            return redirect()->route('learn.test.result', [$a, $attempt])->with('info', 'Waktu habis. Jawaban yang tersimpan telah dinilai otomatis.');
        }
        $attempt->load('answers.question.options', 'assessment');

        return view('employee.test-take', ['a' => $a, 'attempt' => $attempt, 'remaining' => $attempt->remainingSeconds()]);
    }

    public function answer(Request $request, Assignment $assignment, AssessmentAttempt $attempt)
    {
        $a = $this->own($assignment);
        abort_unless($attempt->assignment_id === $a->id, 404);
        $data = $request->validate(['question_id' => 'required|integer', 'option_ids' => 'array', 'option_ids.*' => 'integer']);
        $ok = $this->tests->saveAnswer($attempt, $data['question_id'], $data['option_ids'] ?? []);

        return response()->json(['ok' => $ok, 'remaining' => $attempt->remainingSeconds()], $ok ? 200 : 409);
    }

    public function submit(Assignment $assignment, AssessmentAttempt $attempt)
    {
        $a = $this->own($assignment);
        abort_unless($attempt->assignment_id === $a->id, 404);
        $this->tests->submit($attempt);

        return redirect()->route('learn.test.result', [$a, $attempt]);
    }

    public function result(Assignment $assignment, AssessmentAttempt $attempt)
    {
        $a = $this->own($assignment);
        abort_unless($attempt->assignment_id === $a->id && ! $attempt->isOpen(), 404);
        $attempt->load('assessment', 'answers.question');
        $a->refresh();

        return view('employee.test-result', [
            'a' => $a, 'attempt' => $attempt, 'test' => $attempt->assessment,
            'postStatus' => $attempt->assessment->kind === 'POST' ? $this->tests->postStatus($a) : null,
            'relearn' => $attempt->assessment->kind === 'POST' && ! $attempt->passed && $a->cycle_no > $attempt->cycle_no,
            'next' => $this->flow->resumeUrl($a),
        ]);
    }

    private function test(Assignment $a, string $kind)
    {
        abort_unless(in_array($kind, ['PRE', 'POST'], true), 404);
        $test = $kind === 'PRE' ? $a->program->preAssessment : $a->program->postAssessment;
        abort_unless($test, 404);

        return $test;
    }
}
