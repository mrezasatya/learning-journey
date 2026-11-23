<?php

namespace App\Services;

use App\Events\PostTestExhausted;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pre-test & post-test (Kirkpatrick level 2): BR-21 s.d. BR-29.
 */
class AssessmentService
{
    public function __construct(private LearningFlow $flow) {}

    /**
     * Status post-test pada siklus berjalan.
     *
     * @return array{used:int, max:int, remaining:int, next_at: ?\Illuminate\Support\Carbon, best: ?int}
     */
    public function postStatus(Assignment $a): array
    {
        $post = $a->program->postAssessment;
        $attempts = $a->attempts()->where('assessment_id', $post->id)->where('cycle_no', $a->cycle_no)->whereNotNull('submitted_at')->orderBy('id')->get();
        $last = $attempts->last();
        $next = $last && ! $last->passed ? $last->submitted_at->copy()->addMinutes($post->retry_cooldown_minutes) : null;

        return [
            'used' => $attempts->count(),
            'max' => $post->max_attempts,
            'remaining' => max(0, $post->max_attempts - $attempts->count()),
            'next_at' => $next && $next->isFuture() ? $next : null,
            'best' => $a->attempts()->where('assessment_id', $post->id)->max('score'),
        ];
    }

    /** Alasan tes belum dapat dimulai; null = boleh. */
    public function blockReason(Assignment $a, Assessment $test): ?string
    {
        if (! $a->isOpen()) {
            return 'Penugasan ini sudah selesai atau dikecualikan.';
        }
        if ($test->kind === 'PRE') {
            return $this->flow->preTestDone($a) ? 'Pre-test hanya dikerjakan satu kali (BR-21).' : null;
        }
        if (! $this->flow->preTestDone($a)) {
            return 'Kerjakan pre-test terlebih dahulu.';
        }
        if (! $this->flow->lessonsDone($a)) {
            return 'Post-test terbuka setelah seluruh materi selesai (BR-22).';
        }
        if ($this->flow->postPassed($a)) {
            return 'Anda sudah lulus post-test.';
        }
        $s = $this->postStatus($a);
        if ($s['remaining'] <= 0) {
            return 'Kesempatan pada siklus ini habis. Ulangi materi terlebih dahulu (BR-24).';
        }
        if ($s['next_at']) {
            return 'Remedial dapat dikerjakan mulai '.$s['next_at']->translatedFormat('d M Y H:i').' (jeda '.$test->retry_cooldown_minutes.' menit — BR-23).';
        }

        return null;
    }

    public function openAttempt(Assignment $a, Assessment $test): ?AssessmentAttempt
    {
        return $a->attempts()->where('assessment_id', $test->id)->whereNull('submitted_at')->latest('id')->first();
    }

    /** Memulai tes baru atau melanjutkan tes yang terputus (BR-29). */
    public function startOrResume(Assignment $a, Assessment $test): AssessmentAttempt
    {
        if ($open = $this->openAttempt($a, $test)) {
            if ($open->deadline_at->isPast()) {
                $this->submit($open);
            } else {
                return $open;
            }
        }
        if ($reason = $this->blockReason($a, $test)) {
            throw new \DomainException($reason);
        }

        return DB::transaction(function () use ($a, $test) {
            $this->flow->markStarted($a);
            $questions = $this->pickQuestions($test);
            if ($questions->isEmpty()) {
                throw new \DomainException('Bank soal belum tersedia. Hubungi Admin L&D.');
            }
            $attemptNo = $a->attempts()->where('assessment_id', $test->id)->where('cycle_no', $a->cycle_no)->count() + 1;
            $attempt = AssessmentAttempt::create([
                'assignment_id' => $a->id, 'assessment_id' => $test->id, 'attempt_no' => $attemptNo, 'cycle_no' => $a->cycle_no,
                'started_at' => now(), 'deadline_at' => now()->addMinutes($test->duration_minutes), // timer server
            ]);
            foreach ($questions->values() as $i => $q) {
                $attempt->answers()->create([
                    'question_id' => $q->id, 'sort_order' => $i,
                    'option_order' => $q->options->pluck('id')->shuffle()->values()->all(), // BR-26: opsi diacak
                ]);
            }

            return $attempt;
        });
    }

    /** Soal acak sesuai komposisi tingkat kesulitan (BR-26, BR-28). */
    public function pickQuestions(Assessment $test): Collection
    {
        $pool = Question::with('options')->where('is_active', true)
            ->whereHas('bank', fn ($q) => $q->where('program_id', $test->program_id))->get();
        $n = min($test->question_count, $pool->count());
        $mix = $test->difficulty_mix ?: ['EASY' => 34, 'MEDIUM' => 33, 'HARD' => 33];
        $targets = [];
        foreach ($mix as $d => $pct) {
            $targets[$d] = (int) floor($n * $pct / 100);
        }
        // Sisa pembulatan diberikan ke tingkat dengan persentase terbesar
        arsort($mix);
        $targets[array_key_first($mix)] += $n - array_sum($targets);

        $picked = collect();
        foreach ($targets as $d => $count) {
            $picked = $picked->merge($pool->where('difficulty', $d)->shuffle()->take($count));
        }
        if ($picked->count() < $n) { // stok tingkat tertentu kurang → isi dari tingkat lain
            $picked = $picked->merge($pool->whereNotIn('id', $picked->pluck('id'))->shuffle()->take($n - $picked->count()));
        }

        return $picked->shuffle()->values(); // BR-26: urutan soal diacak
    }

    public function saveAnswer(AssessmentAttempt $attempt, int $questionId, array $optionIds): bool
    {
        if (! $attempt->isOpen() || $attempt->deadline_at->copy()->addSeconds(5)->isPast()) {
            return false;
        }
        $answer = $attempt->answers()->where('question_id', $questionId)->first();
        if (! $answer) {
            return false;
        }
        $valid = array_values(array_intersect(array_map('intval', $optionIds), $answer->option_order));
        $answer->update(['selected_option_ids' => $valid]);

        return true;
    }

    /** Penilaian otomatis (BR-27) + aturan remedial & reset materi (BR-23–BR-25). */
    public function submit(AssessmentAttempt $attempt): AssessmentAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt = AssessmentAttempt::lockForUpdate()->find($attempt->id);
            if ($attempt->submitted_at) {
                return $attempt;
            }
            $attempt->load('answers.question.options', 'assessment', 'assignment.program');
            $correct = 0;
            foreach ($attempt->answers as $ans) {
                $expected = $ans->question->correctOptionIds();
                $given = collect($ans->selected_option_ids ?? [])->map(fn ($i) => (int) $i)->sort()->values()->all();
                $ok = $given === $expected; // MULTI: harus tepat seluruhnya
                $ans->update(['is_correct' => $ok]);
                $correct += $ok ? 1 : 0;
            }
            $total = max(1, $attempt->answers->count());
            $score = (int) round($correct / $total * 100);
            $test = $attempt->assessment;
            $passed = $test->kind === 'POST' ? $score >= $test->passing_score : null;
            $attempt->update(['submitted_at' => min(now(), $attempt->deadline_at), 'score' => $score, 'passed' => $passed]);

            $a = $attempt->assignment;
            if ($test->kind === 'PRE') {
                $a->update(['pre_score' => $score]); // BR-21: tidak menentukan kelulusan
            } elseif ($passed) {
                $a->update(['final_score' => $a->attempts()->where('assessment_id', $test->id)->max('score')]); // BR-25
            } else {
                $used = $a->attempts()->where('assessment_id', $test->id)->where('cycle_no', $a->cycle_no)->whereNotNull('submitted_at')->count();
                if ($used >= $test->max_attempts) {
                    // BR-24: Perlu Mengulang Materi → siklus baru, progres materi dimulai dari awal
                    $prev = $a->cycle_no;
                    $a->update(['cycle_no' => $prev + 1]);
                    AuditLogger::log('assignment.relearn', $a, ['cycle_no' => $prev], ['cycle_no' => $prev + 1]);
                    PostTestExhausted::dispatch($a, $prev);
                }
            }

            return $attempt->fresh();
        });
    }
}
