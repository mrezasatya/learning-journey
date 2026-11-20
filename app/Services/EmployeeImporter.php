<?php

namespace App\Services;

use App\Events\EmployeesChanged;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Position;
use App\Models\User;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Impor data karyawan (US-04) dan upsert berdasarkan NIK (dipakai juga oleh API sinkronisasi ESS).
 * Alur: preview() → admin memeriksa baris baru/berubah/error → commit().
 */
class EmployeeImporter
{
    /** Kolom template impor (US-04 AC1, Brief 7.1). */
    public const COLUMNS = [
        'nik' => 'NIK (wajib, unik)',
        'full_name' => 'Nama lengkap (wajib)',
        'email' => 'Email (opsional)',
        'phone' => 'No. HP (opsional)',
        'position_code' => 'Kode jabatan (wajib)',
        'grade_code' => 'Golongan 1A–7B (wajib)',
        'department_code' => 'Kode departemen (wajib)',
        'work_location_code' => 'Kode lokasi kerja (wajib)',
        'supervisor_nik' => 'NIK atasan langsung (opsional)',
        'employment_status' => 'TETAP / KONTRAK / PROYEK (wajib)',
        'join_date' => 'Tanggal masuk YYYY-MM-DD (wajib)',
        'end_date' => 'Tanggal berakhir (opsional)',
        'is_active' => '1 = aktif, 0 = nonaktif (default 1)',
        'external_ref' => 'ID di ESS (opsional)',
    ];

    private const COMPARE = ['full_name', 'email', 'phone', 'position_id', 'grade_id', 'department_id', 'work_location_id',
        'supervisor_nik', 'employment_status', 'join_date', 'end_date', 'is_active', 'external_ref'];

    private array $lookups = [];

    /**
     * @param  array<int, array<string,mixed>>  $rows
     * @return array{rows: array, summary: array, missing: array}
     */
    public function preview(array $rows): array
    {
        $this->loadLookups();
        $existing = Employee::all()->keyBy('nik');
        $fileNiks = [];
        foreach ($rows as $r) {
            if (! empty($r['nik'])) {
                $fileNiks[] = (string) $r['nik'];
            }
        }
        $counts = array_count_values($fileNiks);

        $out = [];
        $summary = ['NEW' => 0, 'CHANGED' => 0, 'UNCHANGED' => 0, 'ERROR' => 0];
        foreach ($rows as $line => $raw) {
            [$data, $errors] = $this->normalize($raw);
            $nik = $data['nik'] ?? null;
            if ($nik && ($counts[$nik] ?? 0) > 1) {
                $errors[] = 'NIK muncul lebih dari sekali di file';
            }
            if (! empty($data['supervisor_nik']) && $data['supervisor_nik'] === $nik) {
                $errors[] = 'Atasan tidak boleh diri sendiri';
            }
            if (! empty($data['supervisor_nik']) && ! $existing->has($data['supervisor_nik']) && ! in_array($data['supervisor_nik'], $fileNiks, true)) {
                $errors[] = "NIK atasan {$data['supervisor_nik']} tidak ditemukan";
            }

            $status = 'NEW';
            $diff = [];
            if ($errors) {
                $status = 'ERROR';
            } elseif ($existing->has($nik)) {
                $current = $existing[$nik];
                foreach (self::COMPARE as $f) {
                    $old = $current->{$f};
                    $old = $old instanceof Carbon ? $old->format('Y-m-d') : $old;
                    $new = $data[$f] ?? null;
                    if ((string) ($old === false ? 0 : ($old === true ? 1 : $old)) !== (string) ($new === false ? 0 : ($new === true ? 1 : $new))) {
                        $diff[$f] = [$old, $new];
                    }
                }
                $status = $diff ? 'CHANGED' : 'UNCHANGED';
            }
            $summary[$status]++;
            $out[] = ['line' => $line, 'status' => $status, 'data' => $data, 'errors' => $errors, 'diff' => $diff, 'raw' => $raw];
        }

        $missing = $existing->filter(fn ($e) => $e->is_active && ! in_array($e->nik, $fileNiks, true))
            ->map(fn ($e) => ['nik' => $e->nik, 'full_name' => $e->full_name])->values()->all();

        return ['rows' => $out, 'summary' => $summary, 'missing' => $missing];
    }

    /**
     * Menyimpan baris NEW/CHANGED. Baris ERROR dilewati.
     *
     * @return array{created:int, updated:int, deactivated:int, credentials: array}
     */
    public function commit(array $preview, bool $deactivateMissing): array
    {
        $created = $updated = $deactivated = 0;
        $credentials = [];
        $changedIds = [];

        DB::transaction(function () use ($preview, $deactivateMissing, &$created, &$updated, &$deactivated, &$credentials, &$changedIds) {
            foreach ($preview['rows'] as $row) {
                if (! in_array($row['status'], ['NEW', 'CHANGED'], true)) {
                    continue;
                }
                [$employee, $isNew, $password] = $this->upsert($row['data']);
                $changedIds[] = $employee->id;
                if ($isNew) {
                    $created++;
                    if ($password) {
                        $credentials[] = [$employee->nik, $employee->full_name, $password];
                    }
                } else {
                    $updated++;
                }
            }

            if ($deactivateMissing && $preview['missing']) {
                $niks = array_column($preview['missing'], 'nik');
                $ids = Employee::whereIn('nik', $niks)->pluck('id')->all();
                $deactivated = Employee::whereIn('id', $ids)->update(['is_active' => false, 'end_date' => now()->toDateString()]);
                $changedIds = array_merge($changedIds, $ids);
                AuditLogger::log('employee.deactivate_missing', 'Employee', null, ['nik' => $niks]);
            }

            AuditLogger::log('employee.import', 'Employee', null, compact('created', 'updated', 'deactivated') + ['summary' => $preview['summary']]);
        });

        if ($changedIds) {
            EmployeesChanged::dispatch(array_values(array_unique($changedIds)));
        }

        return compact('created', 'updated', 'deactivated', 'credentials');
    }

    /**
     * Upsert satu karyawan berdasarkan NIK + buat akun login bila baru.
     *
     * @return array{0: Employee, 1: bool, 2: ?string}
     */
    public function upsert(array $data): array
    {
        $employee = Employee::firstOrNew(['nik' => $data['nik']]);
        $isNew = ! $employee->exists;
        $before = $employee->exists ? $employee->only(self::COMPARE) : null;
        $employee->fill(array_intersect_key($data, array_flip(self::COMPARE)));
        $employee->save();

        $password = null;
        if (! $employee->user) {
            $password = 'Blj'.random_int(10000, 99999).Str::lower(Str::random(2));
            User::create([
                'employee_id' => $employee->id, 'username' => $employee->nik, 'name' => $employee->full_name,
                'password' => Hash::make($password), 'must_change_password' => true, // BR-01
            ]);
        } else {
            $employee->user->update(['name' => $employee->full_name]);
        }

        AuditLogger::log($isNew ? 'employee.create' : 'employee.update', $employee, $before, $employee->only(self::COMPARE));

        return [$employee, $isNew, $password];
    }

    /** @return array{0: array, 1: array<string>} */
    public function normalize(array $raw): array
    {
        $this->loadLookups();
        $errors = [];
        $get = fn ($k) => isset($raw[$k]) && $raw[$k] !== '' ? trim((string) $raw[$k]) : null;

        $data = ['nik' => $get('nik'), 'full_name' => $get('full_name'), 'email' => $get('email'), 'phone' => $get('phone'),
            'supervisor_nik' => $get('supervisor_nik'), 'external_ref' => $get('external_ref')];

        if (! $data['nik']) {
            $errors[] = 'NIK wajib diisi';
        } elseif (! preg_match('/^[A-Za-z0-9.\-]{3,30}$/', $data['nik'])) {
            $errors[] = 'Format NIK tidak valid';
        }
        if (! $data['full_name']) {
            $errors[] = 'Nama wajib diisi';
        }
        if ($data['email'] && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid';
        }

        foreach (['position_code' => ['position_id', 'positions', 'Jabatan'], 'grade_code' => ['grade_id', 'grades', 'Golongan'],
            'department_code' => ['department_id', 'departments', 'Departemen'], 'work_location_code' => ['work_location_id', 'locations', 'Lokasi']] as $col => [$field, $map, $label]) {
            $code = $get($col);
            if (! $code) {
                $errors[] = "$label wajib diisi";
                $data[$field] = null;
            } elseif (! isset($this->lookups[$map][strtoupper($code)])) {
                $errors[] = "$label '$code' tidak ditemukan di master data";
                $data[$field] = null;
            } else {
                $data[$field] = $this->lookups[$map][strtoupper($code)];
            }
        }

        $status = strtoupper((string) $get('employment_status'));
        if (! array_key_exists($status, Employee::STATUSES)) {
            $errors[] = 'Status karyawan harus TETAP/KONTRAK/PROYEK';
        }
        $data['employment_status'] = $status;

        $data['join_date'] = $this->parseDate($get('join_date'));
        if (! $data['join_date']) {
            $errors[] = 'Tanggal masuk wajib & berformat tanggal';
        }
        $data['end_date'] = $get('end_date') ? $this->parseDate($get('end_date')) : null;
        if ($get('end_date') && ! $data['end_date']) {
            $errors[] = 'Tanggal berakhir tidak valid';
        }

        $active = $get('is_active');
        $data['is_active'] = $active === null ? true : in_array(strtolower($active), ['1', 'ya', 'y', 'true', 'aktif'], true);

        return [$data, $errors];
    }

    private function parseDate(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        if (is_numeric($v) && (int) $v > 20000 && (int) $v < 80000) { // serial date Excel
            return Carbon::create(1899, 12, 30)->addDays((int) $v)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d'] as $fmt) {
            try {
                $d = Carbon::createFromFormat('!'.$fmt, $v);
                if ($d && $d->format($fmt) === $v) {
                    return $d->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function loadLookups(): void
    {
        if ($this->lookups) {
            return;
        }
        $up = fn ($c) => $c->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
        $this->lookups = [
            'positions' => $up(Position::active()->pluck('id', 'code')),
            'grades' => $up(Grade::active()->pluck('id', 'code')),
            'departments' => $up(Department::active()->pluck('id', 'code')),
            'locations' => $up(WorkLocation::active()->pluck('id', 'code')),
        ];
    }
}
