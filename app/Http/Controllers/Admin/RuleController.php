<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentRule;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Position;
use App\Models\Program;
use App\Models\WorkLocation;
use App\Services\AssignmentEngine;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Aturan penugasan berbasis atribut + pratinjau peserta (US-11). */
class RuleController extends Controller
{
    public function __construct(private AssignmentEngine $engine) {}

    public function index(Request $request)
    {
        $rules = AssignmentRule::with('program')->withCount('assignments')
            ->when($request->query('program_id'), fn ($q, $p) => $q->where('program_id', $p))
            ->orderByDesc('is_active')->orderBy('program_id')->get();

        return view('admin.rules.index', ['rules' => $rules, 'options' => $this->options(),
            'programs' => Program::whereIn('status', ['PUBLISHED'])->orderBy('code')->get()]);
    }

    public function create(Request $request)
    {
        return view('admin.rules.form', [
            'rule' => new AssignmentRule(['program_id' => $request->query('program_id'), 'effective_from' => now(), 'is_active' => true,
                'conditions' => ['logic' => 'AND', 'items' => []]]),
            'programs' => Program::published()->orderBy('code')->get(),
            'options' => $this->options(),
        ]);
    }

    public function edit(AssignmentRule $rule)
    {
        return view('admin.rules.form', ['rule' => $rule, 'programs' => Program::published()->orderBy('code')->get(), 'options' => $this->options()]);
    }

    /** Pratinjau jumlah & daftar karyawan yang terkena aturan (US-11 AC2). */
    public function preview(Request $request)
    {
        $data = $this->validated($request);
        $program = Program::findOrFail($data['program_id']);
        $matches = $this->engine->matchingEmployees($data['conditions'], $program, Carbon::parse($data['effective_from']));
        $period = $program->type === 'ANNUAL' ? now()->year : 0;
        $already = \App\Models\Assignment::whereIn('program_id', Program::where('code', $program->code)->pluck('id'))
            ->where('period_year', $period)->pluck('employee_id')->flip();

        return response()->json([
            'total' => $matches->count(),
            'new' => $matches->filter(fn ($e) => ! isset($already[$e->id]))->count(),
            'employees' => $matches->take(200)->map(fn ($e) => [
                'nik' => $e->nik, 'name' => $e->full_name, 'position' => $e->position?->name,
                'department' => $e->department?->name, 'location' => $e->workLocation?->name,
                'already' => isset($already[$e->id]),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $rule = AssignmentRule::create($data + ['created_by' => $request->user()->id]);
        AuditLogger::log('rule.create', $rule, null, $rule->toArray());
        $n = $this->engine->applyRule($rule);

        return redirect()->route('admin.rules.index')->with('success', "Aturan disimpan. {$n} penugasan baru dibuat dan notifikasi dikirim.");
    }

    public function update(Request $request, AssignmentRule $rule)
    {
        $original = $rule->getOriginal();
        $rule->update($this->validated($request));
        AuditLogger::changes('rule.update', $rule, $original);
        $n = $this->engine->applyRule($rule);

        return redirect()->route('admin.rules.index')->with('success', "Aturan diperbarui. {$n} penugasan baru dibuat. Penugasan yang sudah ada tidak dicabut.");
    }

    public function toggle(AssignmentRule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        AuditLogger::log($rule->is_active ? 'rule.activate' : 'rule.deactivate', $rule);
        $n = $rule->is_active ? $this->engine->applyRule($rule) : 0;

        return back()->with('success', $rule->is_active ? "Aturan diaktifkan ({$n} penugasan baru)." : 'Aturan dinonaktifkan. Penugasan yang sudah ada tetap berjalan.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'program_id' => ['required', Rule::exists('programs', 'id')->where('status', 'PUBLISHED')],
            'name' => 'required|string|max:255',
            'effective_from' => 'required|date',
            'logic' => 'required|in:AND,OR',
            'items' => 'array',
            'items.*.field' => ['required', Rule::in(array_keys(AssignmentRule::FIELDS))],
            'items.*.op' => 'required|in:in,not_in',
            'items.*.values' => 'required|array|min:1',
        ], ['program_id.exists' => 'Hanya program Terbit yang dapat ditugaskan.', 'items.*.values.required' => 'Setiap kondisi harus memiliki minimal satu nilai.']);

        return [
            'program_id' => $data['program_id'],
            'name' => $data['name'],
            'effective_from' => $data['effective_from'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'conditions' => ['logic' => $data['logic'], 'items' => array_values(array_map(fn ($i) => [
                'field' => $i['field'], 'op' => $i['op'], 'values' => array_values(array_map('strval', $i['values'])),
            ], $data['items'] ?? []))],
        ];
    }

    /** Pilihan nilai per field (untuk pembuat aturan). */
    private function options(): array
    {
        return [
            'position_id' => Position::active()->orderBy('name')->pluck('name', 'id')->all(),
            'grade_id' => Grade::active()->orderBy('sort_order')->pluck('code', 'id')->all(),
            'department_id' => Department::active()->orderBy('name')->pluck('name', 'id')->all(),
            'division_id' => Division::active()->orderBy('name')->pluck('name', 'id')->all(),
            'work_location_id' => WorkLocation::active()->orderBy('name')->pluck('name', 'id')->all(),
            'location_type' => WorkLocation::TYPES,
            'employment_status' => Employee::STATUSES,
        ];
    }
}
