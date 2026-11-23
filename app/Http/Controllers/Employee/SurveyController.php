<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\SurveyResponse;
use App\Services\LearningFlow;
use Illuminate\Http\Request;

/** Survei reaksi wajib setelah lulus post-test, sebelum sertifikat (BR-30, US-18). */
class SurveyController extends Controller
{
    use Concerns;

    public function __construct(private LearningFlow $flow) {}

    public function show(Assignment $assignment)
    {
        $a = $this->own($assignment);
        if ($a->surveyResponse) {
            return redirect()->route('learn.program', $a);
        }
        if (! $this->flow->postPassed($a)) {
            return redirect()->route('learn.program', $a)->withErrors(['Survei terbuka setelah Anda lulus post-test (BR-30).']);
        }

        return view('employee.survey', ['a' => $a, 'template' => $a->program->effectiveSurveyTemplate()]);
    }

    public function store(Request $request, Assignment $assignment)
    {
        $a = $this->own($assignment);
        abort_if($a->surveyResponse || ! $this->flow->postPassed($a), 409);
        $template = $a->program->effectiveSurveyTemplate();
        $rules = [];
        foreach ($template->items as $item) {
            $rules["answers.{$item['code']}"] = $item['type'] === 'LIKERT' ? 'required|integer|between:1,5' : 'nullable|string|max:2000';
        }
        $data = $request->validate($rules, ['answers.*.required' => 'Mohon isi seluruh butir penilaian 1–5.']);

        SurveyResponse::create(['assignment_id' => $a->id, 'template_id' => $template->id, 'answers' => $data['answers'], 'submitted_at' => now()]);
        $completed = $this->flow->checkCompletion($a);

        return $completed
            ? redirect()->route('learn.program', $a)->with('success', 'Terima kasih! Program selesai dan sertifikat Anda telah terbit. 🎓')
            : redirect()->route('learn.program', $a)->with('info', 'Terima kasih atas masukan Anda. Program akan selesai setelah kehadiran sesi tatap muka tercatat.');
    }
}
