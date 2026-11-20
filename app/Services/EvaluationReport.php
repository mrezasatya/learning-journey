<?php

namespace App\Services;

use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AttemptAnswer;
use App\Models\Program;
use App\Models\SurveyResponse;

/** Laporan Kirkpatrick level 1 & 2 per program (US-26) + analisis butir soal (US-28). */
class EvaluationReport
{
    public function build(Program $program, ?int $year): array
    {
        $ids = Program::where('code', $program->code)->pluck('id');
        $assignments = Assignment::whereIn('program_id', $ids)
            ->when($year, fn ($q) => $q->where(fn ($w) => $w->where('period_year', $year)->orWhere(fn ($x) => $x->where('period_year', 0)->whereYear('assigned_at', $year))))
            ->get();
        $aIds = $assignments->pluck('id');

        // ---- Level 1: reaksi ----
        $responses = SurveyResponse::with('template')->whereIn('assignment_id', $aIds)->get();
        $items = [];
        $open = [];
        foreach ($responses as $r) {
            foreach ($r->template->items as $item) {
                $v = $r->answers[$item['code']] ?? null;
                if ($item['type'] === 'LIKERT' && is_numeric($v)) {
                    $items[$item['code']]['text'] = $item['text'];
                    $items[$item['code']]['values'][] = (int) $v;
                } elseif ($item['type'] === 'TEXT' && filled($v)) {
                    $open[] = ['text' => $v, 'date' => $r->submitted_at];
                }
            }
        }
        $l1 = collect($items)->map(fn ($i) => [
            'text' => $i['text'], 'n' => count($i['values']), 'avg' => round(array_sum($i['values']) / count($i['values']), 2),
            'dist' => array_replace([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], array_count_values($i['values'])),
        ])->values();

        // ---- Level 2: pembelajaran ----
        $post = AssessmentAttempt::whereIn('assignment_id', $aIds)->whereNotNull('submitted_at')->whereHas('assessment', fn ($q) => $q->where('kind', 'POST'))->get();
        $passedAssignments = $post->where('passed', true)->pluck('assignment_id')->unique();
        $pairs = $assignments->filter(fn ($a) => $a->pre_score !== null && $a->final_score !== null);
        $firstAttempts = $post->where('attempt_no', 1)->where('cycle_no', 1);
        $l2 = [
            'participants' => $assignments->count(),
            'completed' => $assignments->filter->isDone()->count(),
            'avg_pre' => $this->avg($assignments->pluck('pre_score')),
            'avg_post' => $this->avg($assignments->pluck('final_score')),
            'avg_gain' => $pairs->count() ? round($pairs->avg(fn ($a) => $a->final_score - $a->pre_score), 1) : null,
            'pairs' => $pairs->count(),
            'first_pass_rate' => $firstAttempts->count() ? round($firstAttempts->where('passed', true)->count() / $firstAttempts->count() * 100, 1) : null,
            'avg_attempts' => $passedAssignments->count() ? round($post->whereIn('assignment_id', $passedAssignments)->count() / $passedAssignments->count(), 2) : null,
            'relearn' => $assignments->where('cycle_no', '>', 1)->count(),
        ];

        return ['l1' => $l1, 'open' => collect($open)->sortByDesc('date')->values(), 'responses' => $responses->count(), 'l2' => $l2,
            'items' => $this->itemAnalysis($post->pluck('id')->merge(
                AssessmentAttempt::whereIn('assignment_id', $aIds)->whereNotNull('submitted_at')->whereHas('assessment', fn ($q) => $q->where('kind', 'PRE'))->pluck('id')
            ))];
    }

    /**
     * Analisis butir: tingkat kesulitan aktual (p = proporsi benar) dan daya beda
     * (D = p kelompok atas 27% − p kelompok bawah 27%, berdasarkan skor percobaan).
     */
    public function itemAnalysis($attemptIds): array
    {
        $attempts = AssessmentAttempt::whereIn('id', $attemptIds)->pluck('score', 'id');
        if ($attempts->count() < 10) {
            return [];
        }
        $sorted = $attempts->sort();
        $k = max(1, (int) round($attempts->count() * 0.27));
        $lower = $sorted->keys()->take($k)->flip();
        $upper = $sorted->keys()->reverse()->take($k)->flip();

        return AttemptAnswer::with('question')->whereIn('attempt_id', $attempts->keys())->whereNotNull('is_correct')->get()
            ->groupBy('question_id')->map(function ($g) use ($lower, $upper) {
                $pu = $g->filter(fn ($x) => isset($upper[$x->attempt_id]));
                $pl = $g->filter(fn ($x) => isset($lower[$x->attempt_id]));
                $p = $g->where('is_correct', true)->count() / $g->count();
                $d = ($pu->count() ? $pu->where('is_correct', true)->count() / $pu->count() : 0) - ($pl->count() ? $pl->where('is_correct', true)->count() / $pl->count() : 0);
                $q = $g->first()->question;

                return ['id' => $q->id, 'text' => $q->text, 'difficulty' => $q->difficulty, 'n' => $g->count(), 'p' => round($p, 2), 'd' => round($d, 2),
                    'flag' => $g->count() < 15 ? 'Data belum cukup' : ($d < 0.2 ? 'Daya beda rendah — tinjau soal' : ($p > 0.95 ? 'Terlalu mudah' : ($p < 0.3 ? 'Terlalu sulit / kunci perlu dicek' : null)))];
            })->sortBy('d')->values()->all();
    }

    private function avg($values): ?float
    {
        $v = collect($values)->filter(fn ($x) => $x !== null);

        return $v->count() ? round($v->avg(), 1) : null;
    }
}
