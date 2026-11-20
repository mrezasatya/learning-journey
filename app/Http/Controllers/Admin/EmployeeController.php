<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Position;
use App\Models\WorkLocation;
use App\Services\EmployeeImporter;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Data karyawan: daftar, detail, edit, impor Excel/CSV (US-04). */
class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = $this->filtered($request)->with(['position', 'grade', 'department', 'workLocation', 'supervisor'])
            ->orderBy('full_name')->paginate(25)->withQueryString();

        return view('admin.employees.index', $this->lookups() + compact('employees'));
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with(['position', 'grade', 'department.division', 'workLocation'])->orderBy('nik')->get()
            ->map(fn ($e) => [$e->nik, $e->full_name, $e->email, $e->position?->code, $e->grade?->code, $e->department?->code,
                $e->department?->division?->name, $e->workLocation?->code, $e->supervisor_nik, $e->employment_status,
                $e->join_date?->format('Y-m-d'), $e->end_date?->format('Y-m-d'), $e->is_active ? 1 : 0]);

        return Spreadsheet::download('karyawan-'.now()->format('Ymd').'.xlsx', ['Karyawan' => [
            ['nik', 'full_name', 'email', 'position_code', 'grade_code', 'department_code', 'divisi', 'work_location_code',
                'supervisor_nik', 'employment_status', 'join_date', 'end_date', 'is_active'], $rows,
        ]]);
    }

    public function show(Employee $employee)
    {
        $employee->load(['position', 'grade', 'department.division', 'workLocation', 'supervisor', 'user',
            'assignments' => fn ($q) => $q->with('program')->orderByDesc('assigned_at')]);
        $subordinates = $employee->subordinates()->where('is_active', true)->orderBy('full_name')->get();

        return view('admin.employees.show', compact('employee', 'subordinates'));
    }

    public function edit(Employee $employee)
    {
        return view('admin.employees.edit', $this->lookups() + compact('employee'));
    }

    public function update(Request $request, Employee $employee, EmployeeImporter $importer)
    {
        $raw = $request->only(array_keys(EmployeeImporter::COLUMNS));
        $raw['nik'] = $employee->nik; // NIK = kunci bisnis, tidak diubah dari form
        $raw['is_active'] = $request->boolean('is_active') ? '1' : '0';
        [$data, $errors] = $importer->normalize($raw);
        if ($data['supervisor_nik'] && ! Employee::where('nik', $data['supervisor_nik'])->exists()) {
            $errors[] = 'NIK atasan tidak ditemukan';
        }
        if ($data['supervisor_nik'] === $employee->nik) {
            $errors[] = 'Atasan tidak boleh diri sendiri';
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }
        [$employee] = $importer->upsert($data);
        \App\Events\EmployeesChanged::dispatch([$employee->id]);

        return redirect()->route('admin.employees.show', $employee)->with('success', 'Data karyawan diperbarui.');
    }

    // ---------- Impor ----------

    public function importForm()
    {
        return view('admin.employees.import');
    }

    public function template()
    {
        $example = ['100500', 'Contoh Nama', 'contoh@perusahaan.co.id', '0812xxxx', 'OPR', '2A', 'PROD', 'PLT-01', '100010', 'KONTRAK', now()->format('Y-m-d'), '', '1', ''];
        $ref = [];
        foreach (Position::active()->orderBy('code')->get() as $p) $ref[] = ['Jabatan', $p->code, $p->name];
        foreach (Grade::active()->orderBy('sort_order')->get() as $g) $ref[] = ['Golongan', $g->code, $g->code];
        foreach (Department::active()->orderBy('code')->get() as $d) $ref[] = ['Departemen', $d->code, $d->name];
        foreach (WorkLocation::active()->orderBy('code')->get() as $l) $ref[] = ['Lokasi', $l->code, $l->name.' ('.$l->type.')'];
        foreach (Employee::STATUSES as $k => $v) $ref[] = ['Status', $k, $v];

        return Spreadsheet::download('template-impor-karyawan.xlsx', [
            'Karyawan' => [array_keys(EmployeeImporter::COLUMNS), [$example]],
            'Petunjuk' => [['Kolom', 'Keterangan'], collect(EmployeeImporter::COLUMNS)->map(fn ($v, $k) => [$k, $v])->values()->all()],
            'Kode Referensi' => [['Jenis', 'Kode', 'Nama'], $ref],
        ]);
    }

    public function preview(Request $request, EmployeeImporter $importer)
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt']], [], ['file' => 'berkas']);
        $file = $request->file('file');
        try {
            $rows = Spreadsheet::read($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Berkas tidak dapat dibaca. Pastikan memakai template .xlsx atau .csv.']);
        }
        if (! $rows || ! array_key_exists('nik', reset($rows))) {
            return back()->withErrors(['file' => 'Kolom "nik" tidak ditemukan. Gunakan template impor.']);
        }

        $preview = $importer->preview($rows);
        $token = Str::random(32);
        Storage::disk('local')->put("imports/$token.json", json_encode($preview));

        return view('admin.employees.preview', compact('preview', 'token') + ['filename' => $file->getClientOriginalName()]);
    }

    public function commit(Request $request, EmployeeImporter $importer)
    {
        $token = $request->validate(['token' => ['required', 'alpha_num', 'size:32']])['token'];
        $path = "imports/$token.json";
        abort_unless(Storage::disk('local')->exists($path), 410, 'Pratinjau kedaluwarsa, unggah ulang berkas.');
        $preview = json_decode(Storage::disk('local')->get($path), true);
        Storage::disk('local')->delete($path);

        $result = $importer->commit($preview, $request->boolean('deactivate_missing'));
        $msg = "Impor selesai: {$result['created']} karyawan baru, {$result['updated']} diperbarui, {$result['deactivated']} dinonaktifkan.";

        if ($result['credentials']) {
            $cred = Str::random(32);
            $file = storage_path("app/private/credentials-$cred.xlsx");
            @mkdir(dirname($file), 0775, true);
            Spreadsheet::writeFile($file, ['Akun baru' => [['NIK', 'Nama', 'Password awal (wajib diganti saat login)'], $result['credentials']]]);
            session()->flash('credentials_token', $cred);
        }

        return redirect()->route('admin.employees.import')->with('success', $msg);
    }

    /** Daftar password awal akun baru — hanya bisa diunduh sekali. */
    public function credentials(string $token)
    {
        abort_unless(ctype_alnum($token) && strlen($token) === 32, 404);
        $file = storage_path("app/private/credentials-$token.xlsx");
        abort_unless(is_file($file), 410, 'Berkas sudah diunduh atau kedaluwarsa.');
        \App\Services\AuditLogger::log('employee.credentials_download', 'Employee');

        return response()->download($file, 'password-awal-karyawan-baru.xlsx')->deleteFileAfterSend();
    }

    private function filtered(Request $request)
    {
        return Employee::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nik', 'like', "%$s%")->orWhere('full_name', 'like', "%$s%")))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('division_id'), fn ($q, $v) => $q->whereHas('department', fn ($d) => $d->where('division_id', $v)))
            ->when($request->query('work_location_id'), fn ($q, $v) => $q->where('work_location_id', $v))
            ->when($request->query('employment_status'), fn ($q, $v) => $q->where('employment_status', $v))
            ->when($request->query('active', '1') !== 'all', fn ($q) => $q->where('is_active', $request->query('active', '1') === '1'));
    }

    private function lookups(): array
    {
        return [
            'positions' => Position::orderBy('name')->get(),
            'grades' => Grade::orderBy('sort_order')->get(),
            'departments' => Department::orderBy('name')->get(),
            'divisions' => Division::orderBy('name')->get(),
            'locations' => WorkLocation::orderBy('name')->get(),
        ];
    }
}
