<?php

namespace Database\Seeders;

use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Employee;
use App\Models\LessonProgress;
use App\Models\Program;
use App\Models\SurveyResponse;
use App\Services\AssessmentService;
use App\Services\AssignmentEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Mensimulasikan aktivitas belajar agar dashboard & laporan berisi data realistis (Tahap 6).
 * - Periode tahun lalu: sebagian besar selesai.
 * - Periode berjalan: campuran selesai / sedang berjalan / belum mulai / overdue.
 * Sertifikat PDF dibuat saat pertama kali diunduh (agar seeding cepat).
 */
class LearningSimulationSeeder extends Seeder
{
    private AssessmentService $tests;

    public function run(AssessmentService $tests, AssignmentEngine $engine): void
    {
        if (app()->environment('testing')) {
            return; // tes otomatis memakai data bersih
        }
        $this->tests = $tests;
        mt_srand(2026);
        $lastYear = now()->year - 1;

        // --- Riwayat periode tahun lalu untuk program tahunan ---
        foreach (Assignment::with('program', 'rule')->where('period_year', now()->year)->whereHas('program', fn ($q) => $q->where('type', 'ANNUAL'))->get() as $cur) {
            $e = $cur->employee;
            if ($e->join_date->year >= $lastYear) {
                continue;
            }
            $prev = $engine->assign($e, $cur->program, 'RULE', rule: $cur->rule, periodYear: $lastYear, at: Carbon::create($lastYear, 1, 1));
            if ($prev && mt_rand(1, 100) <= 92) {
                $this->simulateComplete($prev, Carbon::create($lastYear, mt_rand(2, 12), mt_rand(1, 28)));
            }
        }

        // --- Periode berjalan ---
        $demo = ['100101', '100102'];
        foreach (Assignment::with('program', 'employee.workLocation')->where(fn ($q) => $q->where('period_year', now()->year)->orWhere('period_year', 0))->get() as $a) {
            if (in_array($a->employee->nik, $demo, true) || $a->isDone()) {
                continue;
            }
            $site = $a->employee->workLocation?->type === 'SITE';
            $r = mt_rand(1, 100);
            $completeChance = $site ? 45 : 70;
            if ($r <= $completeChance) {
                $latest = min(now()->subDay()->getTimestamp(), $a->due_at->copy()->addDays(20)->getTimestamp());
                $earliest = $a->assigned_at->copy()->addDays(3)->getTimestamp();
                if ($latest > $earliest) {
                    $this->simulateComplete($a, Carbon::createFromTimestamp(mt_rand($earliest, $latest)));
                }
            } elseif ($r <= $completeChance + 18) {
                $this->simulateInProgress($a, now()->subDays(mt_rand(2, 40)));
            }
        }

        // --- Akun demo 100101 (Budi): satu selesai, satu berjalan, satu belum mulai ---
        $budi = Employee::where('nik', '100101')->first();
        $iso = $budi->assignments()->whereHas('program', fn ($q) => $q->where('code', 'ISO-01'))->where('period_year', now()->year)->first();
        $iso && $this->simulateComplete($iso, now()->subDays(12));
        $k3 = $budi->assignments()->whereHas('program', fn ($q) => $q->where('code', 'K3-01'))->where('period_year', now()->year)->first();
        if ($k3) {
            $this->preTest($k3, now()->subDays(2));
            $k3->update(['status' => Assignment::IN_PROGRESS, 'started_at' => now()->subDays(2)]);
        }
    }

    private function simulateComplete(Assignment $a, Carbon $finish): void
    {
        $start = $finish->copy()->subHours(mt_rand(2, 72));
        $this->preTest($a, $start);
        foreach ($a->program->orderedLessons() as $i => $l) {
            $this->lessonDone($a, $l, $start->copy()->addMinutes(10 + $i * 12));
        }
        $first = $this->scoreFor($a);
        $t = $start->copy()->addMinutes(90);
        $this->attempt($a, 'POST', $t, $first);
        if ($first < 80) {
            $t->addMinutes(mt_rand(65, 600));
            $this->attempt($a, 'POST', $t, mt_rand(80, 100));
        }
        $finish = $t->copy()->addMinutes(5);
        $template = $a->program->effectiveSurveyTemplate();
        $answers = [];
        foreach ($template->items as $item) {
            $answers[$item['code']] = $item['type'] === 'LIKERT' ? [3, 4, 4, 4, 5, 5, 5][mt_rand(0, 6)] - ($item['code'] === 'access' && mt_rand(0, 2) === 0 ? 1 : 0) : (mt_rand(1, 6) === 1 ? ['Materi jelas dan relevan.', 'Mohon video lebih pendek.', 'Sinyal di site kadang lemah, video 360p sangat membantu.', 'Contoh kasus lapangan ditambah.'][mt_rand(0, 3)] : null);
        }
        SurveyResponse::create(['assignment_id' => $a->id, 'template_id' => $template->id, 'answers' => $answers, 'submitted_at' => $finish]);

        $late = $finish->gt($a->due_at);
        $a->refresh();
        $a->fill([
            'status' => $late ? Assignment::COMPLETED_LATE : Assignment::COMPLETED,
            'started_at' => $start, 'completed_at' => $finish, 'completion_method' => 'ONLINE',
            'final_score' => $a->attempts()->whereHas('assessment', fn ($q) => $q->where('kind', 'POST'))->max('score'),
        ])->save();

        $prefix = "BLJ/{$a->program->code}/{$finish->year}/";
        $last = Certificate::where('certificate_no', 'like', $prefix.'%')->orderByDesc('certificate_no')->value('certificate_no');
        Certificate::create([
            'assignment_id' => $a->id,
            'certificate_no' => $prefix.str_pad((string) ($last ? (int) substr($last, -5) + 1 : 1), 5, '0', STR_PAD_LEFT),
            'issued_at' => $finish,
            'valid_until' => $a->program->type === 'ANNUAL' ? Carbon::create($a->period_year, 12, 31) : null,
            'verify_token' => Str::random(40),
        ]);
    }

    private function simulateInProgress(Assignment $a, Carbon $start): void
    {
        $this->preTest($a, $start);
        $lessons = $a->program->orderedLessons();
        $k = mt_rand(0, max(0, $lessons->count() - 1));
        foreach ($lessons->take($k) as $i => $l) {
            $this->lessonDone($a, $l, $start->copy()->addMinutes(10 + $i * 12));
        }
        $a->update(['started_at' => $start, 'status' => $a->status === Assignment::OVERDUE ? Assignment::OVERDUE : Assignment::IN_PROGRESS]);
    }

    private function preTest(Assignment $a, Carbon $at): void
    {
        $score = max(20, min(90, (int) round($this->scoreFor($a) - mt_rand(15, 35))));
        $this->attempt($a, 'PRE', $at, $score);
    }

    private function lessonDone(Assignment $a, $lesson, Carbon $at): void
    {
        LessonProgress::create([
            'assignment_id' => $a->id, 'lesson_id' => $lesson->id, 'cycle_no' => 1,
            'max_position_seconds' => (int) ($lesson->file?->duration_seconds ?? 0),
            'pages_viewed' => $lesson->file?->page_count ? array_fill_keys(array_map('strval', range(1, $lesson->file->page_count)), 35) : null,
            'time_spent_seconds' => mt_rand(60, 600), 'started_at' => $at, 'last_heartbeat_at' => $at->copy()->addMinutes(5), 'completed_at' => $at->copy()->addMinutes(8),
        ]);
    }

    /** Membuat percobaan tes dengan jawaban yang konsisten terhadap nilai target (agar analisis butir soal bermakna). */
    private function attempt(Assignment $a, string $kind, Carbon $at, int $targetScore): void
    {
        $test = $kind === 'PRE' ? $a->program->preAssessment : $a->program->postAssessment;
        $questions = $this->tests->pickQuestions($test);
        $n = $questions->count();
        $attemptNo = $a->attempts()->where('assessment_id', $test->id)->count() + 1;
        $attempt = AssessmentAttempt::create([
            'assignment_id' => $a->id, 'assessment_id' => $test->id, 'attempt_no' => $attemptNo, 'cycle_no' => 1,
            'started_at' => $at, 'deadline_at' => $at->copy()->addMinutes($test->duration_minutes),
        ]);
        $weights = ['EASY' => 1.25, 'MEDIUM' => 1.0, 'HARD' => 0.7];
        $correct = 0;
        foreach ($questions->values() as $i => $q) {
            $p = min(0.98, $targetScore / 100 * $weights[$q->difficulty]);
            $ok = mt_rand(1, 1000) / 1000 <= $p;
            $right = $q->options->where('is_correct', true)->pluck('id')->all();
            $wrong = $q->options->where('is_correct', false)->pluck('id')->all();
            $sel = $ok ? $right : [$wrong[array_rand($wrong)] ?? $right[0]];
            $correct += $ok ? 1 : 0;
            $attempt->answers()->create(['question_id' => $q->id, 'sort_order' => $i, 'option_order' => $q->options->pluck('id')->shuffle()->all(),
                'selected_option_ids' => $sel, 'is_correct' => $ok]);
        }
        $score = (int) round($correct / max(1, $n) * 100);
        $attempt->update(['submitted_at' => $at->copy()->addMinutes(mt_rand(6, $test->duration_minutes)), 'score' => $score,
            'passed' => $kind === 'POST' ? $score >= $test->passing_score : null]);
        if ($kind === 'PRE') {
            $a->update(['pre_score' => $score]);
        }
    }

    private function scoreFor(Assignment $a): int
    {
        return [60, 70, 80, 80, 90, 90, 90, 100, 100][mt_rand(0, 8)];
    }
}
