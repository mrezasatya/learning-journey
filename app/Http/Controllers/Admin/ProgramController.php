<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Program;
use App\Models\SurveyTemplate;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Services\ProgramService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Program training wajib (US-06, US-10) & pengaturan tes/survei. */
class ProgramController extends Controller
{
    public function __construct(private ProgramService $programs) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $programs = Program::withCount(['modules', 'lessons', 'assignments'])
            ->when($status === 'active', fn ($q) => $q->whereIn('status', ['DRAFT', 'PUBLISHED']))
            ->when(in_array($status, ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))
            ->orderBy('code')->orderByDesc('version')->get();

        return view('admin.programs.index', compact('programs', 'status'));
    }

    public function create()
    {
        return view('admin.programs.form', ['program' => new Program(['type' => 'ANNUAL', 'category' => 'K3', 'sequential' => true]), 'templates' => SurveyTemplate::where('is_active', true)->get()]);
    }

    public function store(Request $request, MediaService $media)
    {
        $data = $this->validated($request);
        $data['status'] = 'DRAFT';
        $data['version'] = 1;
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_file_id'] = $media->store($request->file('thumbnail'), 'thumbnails', false)->id;
        }
        $program = Program::create($data);
        $this->programs->ensureDefaults($program);
        AuditLogger::log('program.create', $program, null, $program->toArray());

        return redirect()->route('admin.programs.show', $program)->with('success', 'Program dibuat sebagai Draft. Tambahkan modul & materi.');
    }

    public function show(Program $program)
    {
        $this->programs->ensureDefaults($program);
        $program->load(['modules.lessons.file', 'assessments', 'questionBank', 'rules', 'surveyTemplate', 'parent']);
        $questionStats = $program->questionBank->questions()->selectRaw('difficulty, is_active, count(*) as n')->groupBy('difficulty', 'is_active')->get();
        $versions = Program::where('code', $program->code)->orderByDesc('version')->get();

        return view('admin.programs.show', [
            'program' => $program,
            'problems' => $this->programs->publishProblems($program),
            'questionStats' => $questionStats,
            'versions' => $versions,
            'canDelete' => $this->programs->canDelete($program),
            'hasProcessing' => $program->modules->flatMap->lessons->contains(fn ($l) => $l->file && in_array($l->file->processing_status, ['PENDING', 'PROCESSING'])),
        ]);
    }

    public function edit(Program $program)
    {
        return view('admin.programs.form', ['program' => $program, 'templates' => SurveyTemplate::where('is_active', true)->get()]);
    }

    public function update(Request $request, Program $program, MediaService $media)
    {
        $original = $program->getOriginal();
        $data = $this->validated($request, $program);
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_file_id'] = $media->store($request->file('thumbnail'), 'thumbnails', false)->id;
        }
        $program->update($data);
        AuditLogger::changes('program.update', $program, $original);

        return redirect()->route('admin.programs.show', $program)->with('success', 'Program diperbarui.');
    }

    public function publish(Program $program)
    {
        if ($problems = $this->programs->publish($program)) {
            return back()->withErrors($problems);
        }

        return back()->with('success', 'Program diterbitkan dan dapat ditugaskan.');
    }

    public function archive(Program $program)
    {
        $this->programs->archive($program);

        return back()->with('success', 'Program diarsipkan. Aturan penugasan program ini dinonaktifkan; peserta lama tetap dapat melihat riwayatnya.');
    }

    public function newVersion(Program $program)
    {
        $copy = $this->programs->newVersion($program);

        return redirect()->route('admin.programs.show', $copy)->with('success', "Versi {$copy->version} dibuat sebagai Draft. Peserta versi sebelumnya tidak terdampak (BR-09).");
    }

    public function destroy(Program $program)
    {
        if (! $this->programs->canDelete($program)) {
            return back()->withErrors(['Program sudah memiliki peserta sehingga tidak dapat dihapus — arsipkan saja (BR-08).']);
        }
        AuditLogger::log('program.delete', $program, $program->toArray());
        $program->delete();

        return redirect()->route('admin.programs.index')->with('success', 'Program dihapus.');
    }

    /** Pengaturan pre-test & post-test (US-09 AC3, BR-22–BR-26). */
    public function updateAssessments(Request $request, Program $program)
    {
        $rules = [];
        foreach (['PRE', 'POST'] as $k) {
            $rules += [
                "$k.question_count" => 'required|integer|min:1|max:100',
                "$k.duration_minutes" => 'required|integer|min:1|max:240',
                "$k.mix_easy" => 'required|integer|min:0|max:100',
                "$k.mix_medium" => 'required|integer|min:0|max:100',
                "$k.mix_hard" => 'required|integer|min:0|max:100',
            ];
        }
        $rules += [
            'POST.passing_score' => 'required|integer|min:1|max:100',
            'POST.max_attempts' => 'required|integer|min:1|max:10',
            'POST.retry_cooldown_minutes' => 'required|integer|min:0|max:10080',
            'POST.show_explanation' => 'nullable|boolean',
            'survey_template_id' => 'nullable|exists:survey_templates,id',
        ];
        $data = $request->validate($rules);
        foreach (['PRE', 'POST'] as $k) {
            $mix = ['EASY' => (int) $data[$k]['mix_easy'], 'MEDIUM' => (int) $data[$k]['mix_medium'], 'HARD' => (int) $data[$k]['mix_hard']];
            if (array_sum($mix) !== 100) {
                return back()->withErrors(["Komposisi tingkat kesulitan ".($k === 'PRE' ? 'pre-test' : 'post-test')." harus berjumlah 100%."]);
            }
            $a = Assessment::where('program_id', $program->id)->where('kind', $k)->firstOrFail();
            $original = $a->getOriginal();
            $a->update([
                'question_count' => $data[$k]['question_count'],
                'duration_minutes' => $data[$k]['duration_minutes'],
                'difficulty_mix' => $mix,
            ] + ($k === 'POST' ? [
                'passing_score' => $data['POST']['passing_score'],
                'max_attempts' => $data['POST']['max_attempts'],
                'retry_cooldown_minutes' => $data['POST']['retry_cooldown_minutes'],
                'show_explanation' => $request->boolean('POST.show_explanation'),
            ] : []));
            AuditLogger::changes('assessment.update', $a, $original);
        }
        $program->update(['survey_template_id' => $data['survey_template_id'] ?? null]);

        return back()->with('success', 'Pengaturan tes & survei disimpan.');
    }

    private function validated(Request $request, ?Program $program = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-]+$/i',
                Rule::unique('programs', 'code')->where(fn ($q) => $q->where('version', $program?->version ?? 1))->ignore($program?->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => ['required', Rule::in(array_keys(Program::TYPES))],
            'category' => ['required', Rule::in(array_keys(Program::CATEGORIES))],
            'deadline_days' => 'nullable|integer|min:1|max:'.config('blj.defaults.max_deadline_days'),
            'thumbnail' => 'nullable|image|max:2048',
        ], [
            'deadline_days.max' => 'Tenggat tidak boleh melebihi 365 hari (BR-15).',
            'code.regex' => 'Kode hanya boleh huruf, angka, dan tanda minus.',
        ]);
        unset($data['thumbnail']);
        $data['code'] = strtoupper($data['code']);
        $data['sequential'] = $request->boolean('sequential');
        $data['is_blended'] = $request->boolean('is_blended');
        if ($program && $program->assignments()->exists()) {
            unset($data['code'], $data['type']); // tidak boleh berubah setelah ada peserta
        }

        return $data;
    }
}
