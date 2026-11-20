<?php

namespace App\Http\Controllers\Api;

use App\Events\EmployeesChanged;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\ComplianceReport;
use App\Services\EmployeeImporter;
use Illuminate\Http\Request;

/** "Rumah" integrasi ESS — Brief 9.3. Lihat docs/api/openapi.yaml. */
class IntegrationController extends Controller
{
    /** POST /api/v1/sync/employees — upsert berdasarkan NIK. */
    public function syncEmployees(Request $request, EmployeeImporter $importer)
    {
        $rows = $request->validate(['employees' => 'required|array|max:2000', 'employees.*' => 'array'])['employees'];
        $ok = [];
        $errors = [];
        foreach ($rows as $i => $raw) {
            [$data, $errs] = $importer->normalize($raw);
            if ($data['supervisor_nik'] && ! Employee::where('nik', $data['supervisor_nik'])->exists()
                && ! collect($rows)->pluck('nik')->contains($data['supervisor_nik'])) {
                $errs[] = 'NIK atasan tidak ditemukan';
            }
            if ($errs) {
                $errors[] = ['index' => $i, 'nik' => $data['nik'], 'errors' => $errs];
                continue;
            }
            [$employee, $isNew] = $importer->upsert($data);
            $ok[] = ['nik' => $employee->nik, 'created' => $isNew];
        }
        if ($ok) {
            EmployeesChanged::dispatch(Employee::whereIn('nik', array_column($ok, 'nik'))->pluck('id')->all());
        }
        AuditLogger::log('api.sync_employees', 'Employee', null, ['ok' => count($ok), 'errors' => count($errors)]);

        return response()->json(['processed' => count($ok), 'failed' => count($errors), 'results' => $ok, 'errors' => $errors],
            $errors && ! $ok ? 422 : 200);
    }

    /** GET /api/v1/employees/{nik}/trainings */
    public function trainings(string $nik)
    {
        $e = Employee::where('nik', $nik)->firstOrFail();

        return response()->json([
            'nik' => $e->nik, 'full_name' => $e->full_name,
            'trainings' => Assignment::with('program', 'certificate')->where('employee_id', $e->id)->orderByDesc('assigned_at')->get()->map(fn ($a) => [
                'program_code' => $a->program->code, 'program_name' => $a->program->name, 'program_type' => $a->program->type,
                'period' => $a->period_year ?: null, 'status' => $a->status, 'assigned_at' => $a->assigned_at->toDateString(),
                'due_at' => $a->due_at->toDateString(), 'completed_at' => $a->completed_at?->toDateString(),
                'pre_score' => $a->pre_score, 'final_score' => $a->final_score, 'completion_method' => $a->completion_method,
                'certificate_no' => $a->certificate?->certificate_no,
            ]),
        ]);
    }

    /** GET /api/v1/compliance?period= */
    public function compliance(Request $request, ComplianceReport $report)
    {
        $year = (int) ($request->query('period') ?: now()->year);
        $rows = $report->rows($year);

        return response()->json([
            'period' => $year,
            'summary' => $report->summary($rows),
            'by_program' => $report->breakdown($rows, 'program')->map(fn ($r) => array_intersect_key($r, array_flip(['label', 'total', 'done', 'overdue', 'rate']))),
            'by_division' => $report->breakdown($rows, 'division')->map(fn ($r) => array_intersect_key($r, array_flip(['label', 'total', 'done', 'overdue', 'rate']))),
        ]);
    }

    /** GET /api/v1/certificates/{no} — nomor mengandung "/", kirim dalam bentuk URL-encoded. */
    public function certificate(string $no)
    {
        $c = Certificate::with('assignment.employee', 'assignment.program')->where('certificate_no', urldecode($no))->firstOrFail();

        return response()->json([
            'certificate_no' => $c->certificate_no, 'valid' => $c->isValid(),
            'name' => $c->assignment->employee->full_name, 'program' => $c->assignment->program->name,
            'issued_at' => $c->issued_at->toDateString(), 'valid_until' => $c->valid_until?->toDateString(),
        ]);
    }
}
