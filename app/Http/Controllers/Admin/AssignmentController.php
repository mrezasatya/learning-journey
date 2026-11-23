<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Exemption;
use App\Models\Program;
use App\Models\WorkLocation;
use App\Services\AssignmentEngine;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Daftar penugasan, penugasan manual (US-12), pengecualian/perpanjangan (US-14). */
class AssignmentController extends Controller
{
    public function __construct(private AssignmentEngine $engine) {}

    public function index(Request $request)
    {
        $assignments = $this->filtered($request)
            ->with(['employee.department', 'employee.workLocation', 'program'])
            ->orderBy('due_at')->paginate(30)->withQueryString();

        return view('admin.assignments.index', [
            'assignments' => $assignments,
            'programs' => Program::orderBy('code')->get(),
            'departments' => Department::orderBy('name')->get(),
            'divisions' => Division::orderBy('name')->get(),
            'locations' => WorkLocation::orderBy('name')->get(),
            'years' => Assignment::select('period_year')->distinct()->where('period_year', '>', 0)->orderByDesc('period_year')->pluck('period_year'),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with(['employee.department.division', 'employee.workLocation', 'employee.position', 'program'])
            ->orderBy('due_at')->get()->map(fn ($a) => [
                $a->employee->nik, $a->employee->full_name, $a->employee->position?->name, $a->employee->department?->name,
                $a->employee->department?->division?->name, $a->employee->workLocation?->name, $a->program->code, $a->program->name,
                $a->periodLabel(), $a->statusLabel(), $a->assigned_at->format('Y-m-d'), $a->due_at->format('Y-m-d'),
                $a->completed_at?->format('Y-m-d'), $a->pre_score, $a->final_score, $a->completion_method,
            ]);

        return Spreadsheet::download('penugasan-'.now()->format('Ymd').'.xlsx', ['Penugasan' => [
            ['NIK', 'Nama', 'Jabatan', 'Departemen', 'Divisi', 'Lokasi', 'Kode program', 'Program', 'Periode', 'Status',
                'Ditugaskan', 'Tenggat', 'Selesai', 'Pre-test', 'Nilai akhir', 'Metode'], $rows]]);
    }

    public function show(Assignment $assignment)
    {
        $assignment->load(['employee.supervisor', 'program.modules.lessons', 'attempts.assessment', 'lessonProgress', 'surveyResponse',
            'certificate', 'exemptions.approver', 'manualCompletion.recorder', 'followup', 'rule']);

        return view('admin.assignments.show', ['a' => $assignment]);
    }

    public function create()
    {
        return view('admin.assignments.create', ['programs' => Program::published()->orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'program_id' => ['required', Rule::exists('programs', 'id')->where('status', 'PUBLISHED')],
            'niks' => 'nullable|string|max:20000',
            'employee_ids' => 'nullable|array',
            'file' => 'nullable|file|mimes:xlsx,csv,txt|max:5120',
            'due_at' => 'nullable|date|after:today',
            'period_year' => 'nullable|integer|min:2020|max:2100',
        ]);
        $program = Program::findOrFail($data['program_id']);

        $niks = preg_split('/[\s,;]+/', (string) ($data['niks'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if ($request->hasFile('file')) {
            foreach (Spreadsheet::read($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension()) as $row) {
                if (! empty($row['nik'])) $niks[] = (string) $row['nik'];
            }
        }
        $employees = Employee::where('is_active', true)
            ->where(fn ($q) => $q->whereIn('nik', $niks)->orWhereIn('id', $data['employee_ids'] ?? []))->get();
        $unknown = array_values(array_diff(array_unique($niks), $employees->pluck('nik')->all()));
        if ($employees->isEmpty()) {
            return back()->withInput()->withErrors(['niks' => 'Tidak ada karyawan aktif yang cocok.'.($unknown ? ' NIK tidak dikenal: '.implode(', ', array_slice($unknown, 0, 20)) : '')]);
        }

        $due = isset($data['due_at']) ? Carbon::parse($data['due_at'])->endOfDay() : null;
        if ($due && $due->gt(now()->addDays(365))) {
            return back()->withInput()->withErrors(['due_at' => 'Tenggat tidak boleh melebihi 1 tahun (BR-14).']);
        }
        $created = 0;
        $skipped = 0;
        foreach ($employees as $e) {
            $a = $this->engine->assign($e, $program, 'MANUAL', periodYear: $data['period_year'] ?? null, dueAt: $due, assignedBy: $request->user()->id);
            $a ? $created++ : $skipped++;
        }
        AuditLogger::log('assignment.manual', $program, null, ['employees' => $employees->pluck('nik'), 'created' => $created, 'skipped' => $skipped]);

        $msg = "{$created} penugasan dibuat.".($skipped ? " {$skipped} dilewati karena sudah ditugaskan pada periode yang sama (BR-12)." : '')
            .($unknown ? ' NIK tidak dikenal/nonaktif: '.implode(', ', array_slice($unknown, 0, 20)) : '');

        return redirect()->route('admin.assignments.index', ['program_id' => $program->id])->with('success', $msg);
    }

    /** Pencarian karyawan untuk form penugasan manual. */
    public function searchEmployees(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(Employee::where('is_active', true)
            ->where(fn ($w) => $w->where('nik', 'like', "%$q%")->orWhere('full_name', 'like', "%$q%"))
            ->with('department')->limit(20)->get()->map(fn ($e) => ['id' => $e->id, 'nik' => $e->nik, 'name' => $e->full_name, 'department' => $e->department?->name]));
    }

    public function exempt(Request $request, Assignment $assignment, MediaService $media)
    {
        $data = $request->validate([
            'type' => 'required|in:EXTEND,EXEMPT',
            'reason_category' => ['required', Rule::in(array_keys(Exemption::REASONS))],
            'reason' => 'required|string|min:10|max:2000',
            'new_due_at' => 'required_if:type,EXTEND|nullable|date|after:today',
            'evidence' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ], ['reason.min' => 'Alasan wajib diisi minimal 10 karakter (BR-16).']);

        $evidenceId = $request->hasFile('evidence') ? $media->store($request->file('evidence'), 'evidence', false)->id : null;
        try {
            $this->engine->exempt($assignment, $data['type'], $data['reason_category'], $data['reason'],
                isset($data['new_due_at']) ? Carbon::parse($data['new_due_at']) : null, $evidenceId);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['exempt' => $e->getMessage()]);
        }

        return back()->with('success', $data['type'] === 'EXTEND' ? 'Tenggat diperpanjang.' : 'Karyawan dikecualikan dari penugasan ini.');
    }

    /** Penyelesaian manual untuk training offline — bukti wajib (BR-44, US-24). */
    public function manualComplete(Request $request, Assignment $assignment, MediaService $media, \App\Services\LearningFlow $flow)
    {
        abort_unless($assignment->isOpen(), 409, 'Penugasan tidak terbuka.');
        $data = $request->validate([
            'evidence' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'score' => 'nullable|integer|min:0|max:100',
            'notes' => 'required|string|min:10|max:2000',
        ], ['evidence.required' => 'Bukti (daftar hadir/foto) wajib diunggah (BR-44).', 'notes.min' => 'Keterangan minimal 10 karakter.']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($assignment, $request, $media, $flow, $data) {
            $file = $media->store($request->file('evidence'), 'evidence', false);
            $assignment->manualCompletion()->create(['evidence_file_id' => $file->id, 'score' => $data['score'] ?? null,
                'notes' => $data['notes'], 'recorded_by' => $request->user()->id]);
            $flow->complete($assignment, 'MANUAL', $data['score'] ?? null);
            AuditLogger::log('assignment.manual_complete', $assignment, null, ['score' => $data['score'] ?? null, 'notes' => $data['notes'], 'evidence_file_id' => $file->id]);
        });

        return back()->with('success', 'Penugasan ditandai selesai secara manual dan sertifikat diterbitkan.');
    }

    private function filtered(Request $request)
    {
        return Assignment::query()
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)
                ->when($request->query('q'), fn ($w, $s) => $w->where(fn ($x) => $x->where('nik', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%")))
                ->when($request->query('department_id'), fn ($w, $v) => $w->where('department_id', $v))
                ->when($request->query('division_id'), fn ($w, $v) => $w->whereHas('department', fn ($d) => $d->where('division_id', $v)))
                ->when($request->query('work_location_id'), fn ($w, $v) => $w->where('work_location_id', $v))
                ->when($request->query('location_type'), fn ($w, $v) => $w->whereHas('workLocation', fn ($l) => $l->where('type', $v))))
            ->when($request->query('program_id'), fn ($q, $v) => $q->where('program_id', $v))
            ->when($request->query('status'), function ($q, $v) {
                return match ($v) {
                    'open' => $q->open(),
                    'done' => $q->done(),
                    'due30' => $q->whereIn('status', [Assignment::NOT_STARTED, Assignment::IN_PROGRESS])->where('due_at', '<=', now()->addDays(30)),
                    default => $q->where('status', $v),
                };
            })
            ->when($request->query('period'), fn ($q, $v) => $q->where('period_year', $v));
    }
}
