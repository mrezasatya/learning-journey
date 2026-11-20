<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Program;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/** Siklus hidup program: Draft → Terbit → Diarsipkan, dan versi (BR-07, BR-08, BR-09). */
class ProgramService
{
    /** Daftar masalah yang menghalangi penerbitan. Kosong = siap terbit. */
    public function publishProblems(Program $program): array
    {
        $problems = [];
        $program->load(['modules.lessons.file', 'questionBank.questions', 'assessments']);
        if ($program->modules->isEmpty()) {
            $problems[] = 'Program belum memiliki modul (BR-07).';
        }
        foreach ($program->modules as $m) {
            if ($m->lessons->isEmpty()) {
                $problems[] = "Modul \"{$m->title}\" belum memiliki materi (BR-07).";
            }
            foreach ($m->lessons as $l) {
                if ($l->file && ! $l->file->isReady()) {
                    $problems[] = "Materi \"{$l->title}\" masih diproses atau gagal diproses.";
                }
            }
        }
        $active = $program->questionBank?->questions->where('is_active', true)->count() ?? 0;
        foreach (['PRE', 'POST'] as $kind) {
            $a = $program->assessments->firstWhere('kind', $kind);
            if (! $a) {
                $problems[] = ($kind === 'PRE' ? 'Pre-test' : 'Post-test').' belum diatur.';
            } elseif ($active < $a->question_count) {
                $problems[] = "Bank soal aktif ({$active}) kurang dari jumlah soal ".($kind === 'PRE' ? 'pre-test' : 'post-test')." ({$a->question_count}).";
            }
        }

        return array_values(array_unique($problems));
    }

    public function publish(Program $program): array
    {
        if ($problems = $this->publishProblems($program)) {
            return $problems;
        }
        DB::transaction(function () use ($program) {
            $program->update(['status' => 'PUBLISHED', 'published_at' => now()]);
            // Versi baru menggantikan versi sebelumnya: versi lama diarsipkan, aturan penugasan dipindah.
            if ($program->parent_program_id && $parent = $program->parent) {
                $parent->rules()->update(['program_id' => $program->id]);
                if ($parent->status === 'PUBLISHED') {
                    $parent->update(['status' => 'ARCHIVED']);
                }
            }
            AuditLogger::log('program.publish', $program, null, ['version' => $program->version]);
        });

        return [];
    }

    public function archive(Program $program): void
    {
        $program->update(['status' => 'ARCHIVED']);
        $program->rules()->update(['is_active' => false]);
        AuditLogger::log('program.archive', $program);
    }

    /** Program yang sudah punya peserta tidak dapat dihapus (BR-08). */
    public function canDelete(Program $program): bool
    {
        return ! $program->assignments()->exists();
    }

    /** Membuat salinan program sebagai versi baru berstatus Draft (BR-09, US-10). */
    public function newVersion(Program $program): Program
    {
        return DB::transaction(function () use ($program) {
            $program->load(['modules.lessons', 'questionBank.questions.options', 'assessments']);
            $latest = Program::where('code', $program->code)->max('version');
            $copy = $program->replicate(['status', 'published_at']);
            $copy->fill(['version' => $latest + 1, 'parent_program_id' => $program->id, 'status' => 'DRAFT'])->save();

            $moduleMap = [];
            foreach ($program->modules as $m) {
                $nm = $m->replicate();
                $nm->program_id = $copy->id;
                $nm->save();
                $moduleMap[$m->id] = $nm->id;
                foreach ($m->lessons as $l) {
                    $nl = $l->replicate();
                    $nl->module_id = $nm->id;
                    $nl->save(); // berkas dipakai bersama (tidak diduplikasi)
                }
            }
            if ($bank = $program->questionBank) {
                $nb = $bank->replicate();
                $nb->program_id = $copy->id;
                $nb->save();
                foreach ($bank->questions as $q) {
                    $nq = $q->replicate();
                    $nq->bank_id = $nb->id;
                    $nq->module_id = $q->module_id ? ($moduleMap[$q->module_id] ?? null) : null;
                    $nq->save();
                    foreach ($q->options as $o) {
                        $no = $o->replicate();
                        $no->question_id = $nq->id;
                        $no->save();
                    }
                }
            }
            foreach ($program->assessments as $a) {
                $na = $a->replicate();
                $na->program_id = $copy->id;
                $na->save();
            }
            AuditLogger::log('program.new_version', $copy, ['from_id' => $program->id], ['version' => $copy->version]);

            return $copy;
        });
    }

    /** Pastikan bank soal & pengaturan pre/post-test default tersedia. */
    public function ensureDefaults(Program $program): void
    {
        $program->questionBank()->firstOrCreate([], ['name' => 'Bank Soal '.$program->code]);
        foreach (['PRE', 'POST'] as $kind) {
            Assessment::firstOrCreate(['program_id' => $program->id, 'kind' => $kind], [
                'question_count' => Settings::get('question_count'),
                'duration_minutes' => Settings::get('duration_minutes'),
                'passing_score' => Settings::get('passing_score'),
                'max_attempts' => Settings::get('max_attempts'),
                'retry_cooldown_minutes' => Settings::get('retry_cooldown_minutes'),
                'difficulty_mix' => ['EASY' => 30, 'MEDIUM' => 50, 'HARD' => 20],
            ]);
        }
    }
}
