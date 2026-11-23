<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\SessionParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alur belajar karyawan (BR-17 s.d. BR-20, BR-32):
 *   Pre-test → Materi (berurutan) → Post-test → [Sesi klasikal bila blended] → Survei → Sertifikat
 */
class LearningFlow
{
    public const STEP_PRE = 'PRE_TEST';
    public const STEP_LESSONS = 'LESSONS';
    public const STEP_POST = 'POST_TEST';
    public const STEP_SURVEY = 'SURVEY';
    public const STEP_SESSION = 'SESSION';
    public const STEP_DONE = 'DONE';
    public const STEP_EXEMPTED = 'EXEMPTED';

    private const TOLERANCE_SECONDS = 2;

    public function __construct(private CertificateService $certificates) {}

    /**
     * Materi berurutan + status pada siklus berjalan.
     *
     * @return Collection<int, array{lesson: Lesson, progress: ?LessonProgress, done: bool, locked: bool}>
     */
    public function lessons(Assignment $a): Collection
    {
        $lessons = $a->program->orderedLessons()->load('file', 'module');
        $progress = LessonProgress::where('assignment_id', $a->id)->where('cycle_no', $a->cycle_no)->get()->keyBy('lesson_id');
        $preDone = $this->preTestDone($a);
        $prevDone = true;

        return $lessons->map(function (Lesson $l) use ($progress, $a, $preDone, &$prevDone) {
            $p = $progress[$l->id] ?? null;
            $done = (bool) $p?->completed_at;
            $locked = ! $preDone || ($a->program->sequential && ! $prevDone); // BR-18, BR-21
            $prevDone = $prevDone && $done;

            return ['lesson' => $l, 'progress' => $p, 'done' => $done, 'locked' => $locked];
        })->values();
    }

    public function preTestDone(Assignment $a): bool
    {
        $pre = $a->program->preAssessment;

        return ! $pre || $a->attempts()->where('assessment_id', $pre->id)->whereNotNull('submitted_at')->exists();
    }

    public function lessonsDone(Assignment $a): bool
    {
        return $this->lessons($a)->every(fn ($x) => $x['done']);
    }

    public function postPassed(Assignment $a): bool
    {
        $post = $a->program->postAssessment;

        return ! $post || $a->attempts()->where('assessment_id', $post->id)->where('passed', true)->exists();
    }

    public function sessionAttended(Assignment $a): bool
    {
        if (! $a->program->is_blended) {
            return true;
        }

        return SessionParticipant::where('employee_id', $a->employee_id)->where('attendance_status', 'PRESENT')
            ->whereHas('session', fn ($q) => $q->whereIn('program_id', \App\Models\Program::where('code', $a->program->code)->pluck('id'))
                ->where('start_at', '>=', $a->assigned_at->copy()->startOfYear()))
            ->exists();
    }

    public function currentStep(Assignment $a): string
    {
        if ($a->status === Assignment::EXEMPTED) {
            return self::STEP_EXEMPTED;
        }
        if ($a->isDone()) {
            return self::STEP_DONE;
        }
        if (! $this->preTestDone($a)) {
            return self::STEP_PRE;
        }
        if (! $this->lessonsDone($a)) {
            return self::STEP_LESSONS;
        }
        if (! $this->postPassed($a)) {
            return self::STEP_POST;
        }
        if (! $a->surveyResponse) {
            return self::STEP_SURVEY;
        }
        if (! $this->sessionAttended($a)) {
            return self::STEP_SESSION;
        }

        return self::STEP_DONE;
    }

    /** Persentase progres untuk kartu beranda. */
    public function percent(Assignment $a): int
    {
        if ($a->isDone()) {
            return 100;
        }
        $lessons = $this->lessons($a);
        $total = $lessons->count() + 3 + ($a->program->is_blended ? 1 : 0);
        $done = $lessons->where('done', true)->count()
            + ($this->preTestDone($a) ? 1 : 0) + ($this->postPassed($a) ? 1 : 0) + ($a->surveyResponse ? 1 : 0)
            + ($a->program->is_blended && $this->sessionAttended($a) ? 1 : 0);

        return (int) floor($done / max(1, $total) * 100);
    }

    /** URL "Lanjutkan" ke posisi terakhir (US-15 AC2, BR-20). */
    public function resumeUrl(Assignment $a): string
    {
        return match ($this->currentStep($a)) {
            self::STEP_PRE => route('learn.test.intro', [$a, 'PRE']),
            self::STEP_LESSONS => ($next = $this->lessons($a)->first(fn ($x) => ! $x['done'] && ! $x['locked']))
                ? route('learn.lesson', [$a, $next['lesson']]) : route('learn.program', $a),
            self::STEP_POST => route('learn.test.intro', [$a, 'POST']),
            self::STEP_SURVEY => route('learn.survey', $a),
            default => route('learn.program', $a),
        };
    }

    public function markStarted(Assignment $a): void
    {
        if (! $a->started_at) {
            $a->started_at = now();
            if ($a->status === Assignment::NOT_STARTED) {
                $a->status = Assignment::IN_PROGRESS;
            }
            $a->save();
        }
    }

    // ------------------------------------------------------------------
    // Pelacakan materi (BR-19, BR-20)
    // ------------------------------------------------------------------

    public function progressFor(Assignment $a, Lesson $l): LessonProgress
    {
        return LessonProgress::firstOrCreate(
            ['assignment_id' => $a->id, 'lesson_id' => $l->id, 'cycle_no' => $a->cycle_no],
            ['started_at' => now(), 'last_heartbeat_at' => now(), 'max_position_seconds' => 0, 'last_position_seconds' => 0, 'time_spent_seconds' => 0]
        );
    }

    /**
     * Video: posisi terjauh hanya boleh bertambah sebanding waktu nyata (anti-seek/percepat di sisi server).
     */
    public function recordVideo(Assignment $a, Lesson $l, float $position): LessonProgress
    {
        $p = $this->progressFor($a, $l);
        $duration = (int) ($l->file?->duration_seconds ?? 0);
        $elapsed = max(0, now()->getTimestamp() - ($p->last_heartbeat_at ?? $p->started_at ?? now())->getTimestamp());
        $allowed = $p->max_position_seconds + (int) ceil(min($elapsed, 120) * 1.1) + 3;
        $position = max(0, min($position, $duration ?: $position));
        $newMax = max($p->max_position_seconds, (int) min($position, $allowed));

        $p->fill([
            'max_position_seconds' => $newMax,
            'last_position_seconds' => (int) min($position, $newMax),
            'time_spent_seconds' => $p->time_spent_seconds + min($elapsed, 60),
            'last_heartbeat_at' => now(),
        ]);
        if (! $p->completed_at && $duration > 0 && $newMax >= floor($duration * (float) $l->completion_threshold)) {
            $p->completed_at = now();
        }
        $p->save();

        return $p;
    }

    /** Dokumen: action=open (mulai menghitung waktu halaman) | done (halaman selesai dibaca). */
    public function recordPage(Assignment $a, Lesson $l, int $page, string $action): array
    {
        $p = $this->progressFor($a, $l);
        $pages = $p->pages_viewed ?? [];
        $count = (int) ($l->file?->page_count ?? 0);
        if ($page < 1 || ($count && $page > $count)) {
            return ['ok' => false, 'message' => 'Halaman tidak valid.'];
        }
        $elapsed = now()->getTimestamp() - ($p->last_heartbeat_at ?? now())->getTimestamp();
        if ($action === 'open') {
            $p->last_heartbeat_at = now();
            $p->time_spent_seconds += min(max(0, $elapsed), 120);
            $p->save();

            return ['ok' => true, 'pages' => array_keys($pages)];
        }
        $min = $l->effectiveMinSeconds();
        if (! isset($pages[(string) $page]) && $elapsed < $min - self::TOLERANCE_SECONDS) {
            return ['ok' => false, 'message' => 'Waktu baca minimum halaman ini belum terpenuhi.', 'wait' => $min - $elapsed];
        }
        $pages[(string) $page] = max($pages[(string) $page] ?? 0, $elapsed);
        $p->pages_viewed = $pages;
        $p->time_spent_seconds += min(max(0, $elapsed), 300);
        $p->last_heartbeat_at = now();
        if (! $p->completed_at && $count && count($pages) >= $count) {
            $p->completed_at = now();
        }
        $p->save();

        return ['ok' => true, 'pages' => array_keys($pages), 'completed' => (bool) $p->completed_at];
    }

    /** Teks/gambar: "Tandai selesai" setelah waktu minimum (BR-19). */
    public function completeTimed(Assignment $a, Lesson $l): array
    {
        $p = $this->progressFor($a, $l);
        $elapsed = now()->getTimestamp() - $p->started_at->getTimestamp();
        $min = $l->effectiveMinSeconds();
        if ($elapsed < $min - self::TOLERANCE_SECONDS) {
            return ['ok' => false, 'wait' => $min - $elapsed];
        }
        $p->update(['completed_at' => $p->completed_at ?? now(), 'time_spent_seconds' => max($p->time_spent_seconds, $elapsed)]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Penyelesaian (BR-32, BR-41)
    // ------------------------------------------------------------------

    public function checkCompletion(Assignment $a): bool
    {
        $a->refresh();
        if ($a->isDone() || $a->status === Assignment::EXEMPTED || $this->currentStep($a) !== self::STEP_DONE) {
            return false;
        }
        DB::transaction(function () use ($a) {
            $this->complete($a, 'ONLINE');
        });

        return true;
    }

    /** Menandai selesai + menerbitkan sertifikat. Dipakai juga oleh penyelesaian manual (BR-44). */
    public function complete(Assignment $a, string $method, ?int $score = null): void
    {
        $now = now();
        $late = $a->status === Assignment::OVERDUE || $now->gt($a->due_at);
        $best = $a->attempts()->whereHas('assessment', fn ($q) => $q->where('kind', 'POST'))->whereNotNull('score')->max('score');
        $a->fill([
            'status' => $late ? Assignment::COMPLETED_LATE : Assignment::COMPLETED, // BR-41
            'completed_at' => $now,
            'completion_method' => $method,
            'final_score' => $score ?? $best, // BR-25: nilai tertinggi
            'started_at' => $a->started_at ?? $now,
        ])->save();
        $a->followup()->where('status', 'RECOMMENDED')->update(['status' => 'CANCELLED', 'notes' => 'Dibatalkan otomatis: program telah diselesaikan.']);
        $this->certificates->issue($a);
        event(new \App\Events\AssignmentCompleted($a));
    }
}
