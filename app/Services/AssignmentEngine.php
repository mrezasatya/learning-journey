<?php

namespace App\Services;

use App\Events\AssignmentCreated;
use App\Models\Assignment;
use App\Models\AssignmentRule;
use App\Models\Employee;
use App\Models\Exemption;
use App\Models\Program;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mesin penugasan training wajib.
 *  - Aturan berbasis atribut, dinamis (BR-10, BR-11, US-11)
 *  - Penugasan manual (US-12)
 *  - Wajib Tahunan setiap 1 Januari (US-13)
 *  - Tenggat (BR-14, BR-15) & pengecualian/perpanjangan (BR-16, US-14)
 *  - Tanpa duplikasi pada periode yang sama (BR-12)
 */
class AssignmentEngine
{
    // ------------------------------------------------------------------
    // Evaluasi aturan
    // ------------------------------------------------------------------

    /** Nilai atribut karyawan untuk sebuah field aturan. */
    public function attribute(Employee $e, string $field): mixed
    {
        return match ($field) {
            'division_id' => $e->department?->division_id,
            'location_type' => $e->workLocation?->type,
            default => $e->{$field},
        };
    }

    /**
     * conditions = {"logic":"AND"|"OR","items":[{"field":"position_id","op":"in"|"not_in","values":[...]}]}
     * Daftar kondisi kosong = berlaku untuk semua karyawan aktif.
     */
    public function matches(array $conditions, Employee $e): bool
    {
        $items = $conditions['items'] ?? [];
        if (! $items) {
            return true;
        }
        $results = array_map(function ($c) use ($e) {
            if (! array_key_exists($c['field'] ?? '', AssignmentRule::FIELDS)) {
                throw new InvalidArgumentException('Field aturan tidak dikenal: '.($c['field'] ?? ''));
            }
            $value = (string) $this->attribute($e, $c['field']);
            $in = in_array($value, array_map('strval', $c['values'] ?? []), true);

            return ($c['op'] ?? 'in') === 'not_in' ? ! $in : $in;
        }, $items);

        return strtoupper($conditions['logic'] ?? 'AND') === 'OR' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /** Karyawan aktif yang memenuhi kondisi (untuk pratinjau — US-11 AC2). */
    public function matchingEmployees(array $conditions, ?Program $program = null, ?CarbonInterface $effectiveFrom = null): Collection
    {
        return $this->activeEmployees()
            ->filter(fn ($e) => $this->matches($conditions, $e))
            ->filter(fn ($e) => ! $program || $program->type !== 'ONBOARDING' || ! $effectiveFrom || $e->join_date->gte($effectiveFrom))
            ->values();
    }

    // ------------------------------------------------------------------
    // Penugasan
    // ------------------------------------------------------------------

    /** Menerapkan satu aturan ke seluruh karyawan aktif. @return int jumlah penugasan baru */
    public function applyRule(AssignmentRule $rule, ?CarbonInterface $at = null): int
    {
        $program = $rule->program;
        if (! $rule->is_active || ! $program->isPublished()) {
            return 0;
        }
        $created = 0;
        foreach ($this->matchingEmployees($rule->conditions, $program, $rule->effective_from) as $e) {
            if ($this->assign($e, $program, 'RULE', rule: $rule, at: $at)) {
                $created++;
            }
        }

        return $created;
    }

    /** Evaluasi ulang semua aturan aktif untuk karyawan tertentu (BR-11, US-04 AC5). */
    public function syncEmployees(array $employeeIds): int
    {
        $rules = AssignmentRule::with('program')->where('is_active', true)
            ->whereHas('program', fn ($q) => $q->where('status', 'PUBLISHED'))->get();
        $employees = Employee::with(['department', 'workLocation'])->whereIn('id', $employeeIds)->where('is_active', true)->get();
        $created = 0;
        foreach ($employees as $e) {
            foreach ($rules as $rule) {
                if ($rule->program->type === 'ONBOARDING' && $e->join_date->lt($rule->effective_from)) {
                    continue; // BR-13
                }
                if ($this->matches($rule->conditions, $e) && $this->assign($e, $rule->program, 'RULE', rule: $rule)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /** Penugasan ulang Wajib Tahunan untuk periode baru (US-13). */
    public function assignAnnual(int $year, ?CarbonInterface $at = null): int
    {
        $at ??= Carbon::create($year, 1, 1);
        $created = 0;
        $rules = AssignmentRule::with('program')->where('is_active', true)
            ->whereHas('program', fn ($q) => $q->where('status', 'PUBLISHED')->where('type', 'ANNUAL'))->get();
        foreach ($rules as $rule) {
            foreach ($this->matchingEmployees($rule->conditions) as $e) {
                if ($this->assign($e, $rule->program, 'RULE', rule: $rule, periodYear: $year, at: $at)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Menugaskan satu program ke satu karyawan. Mengembalikan null bila sudah ada pada periode yang sama (BR-12).
     */
    public function assign(Employee $e, Program $program, string $source, ?AssignmentRule $rule = null, ?int $periodYear = null,
        ?CarbonInterface $at = null, ?CarbonInterface $dueAt = null, ?int $assignedBy = null): ?Assignment
    {
        if (! $program->isPublished()) {
            throw new InvalidArgumentException('Hanya program Terbit yang dapat ditugaskan (BR-08).');
        }
        if (! $e->is_active) {
            return null;
        }
        $at = Carbon::instance($at ?? now());
        $period = $program->type === 'ANNUAL' ? ($periodYear ?? $at->year) : 0;

        // BR-12: satu karyawan, satu program (semua versi dengan kode sama), satu periode
        $sameCode = Program::where('code', $program->code)->pluck('id');
        if (Assignment::where('employee_id', $e->id)->whereIn('program_id', $sameCode)->where('period_year', $period)->exists()) {
            return null;
        }

        $due = $dueAt ? Carbon::instance($dueAt) : $this->dueDate($program, $at, $period);
        $maxDue = $at->copy()->addDays((int) config('blj.defaults.max_deadline_days'))->endOfDay();
        if ($due->gt($maxDue)) {
            $due = $maxDue; // BR-14: maksimal 1 tahun
        }

        $assignment = DB::transaction(fn () => Assignment::create([
            'employee_id' => $e->id,
            'program_id' => $program->id,
            'program_version' => $program->version,
            'period_year' => $period,
            'source' => $source,
            'rule_id' => $rule?->id,
            'assigned_at' => $at,
            'due_at' => $due,
            'status' => $due->isPast() ? Assignment::OVERDUE : Assignment::NOT_STARTED,
            'assigned_by' => $assignedBy,
        ]));
        AssignmentCreated::dispatch($assignment);

        return $assignment;
    }

    /** Tenggat penyelesaian (BR-14, BR-15). */
    public function dueDate(Program $program, CarbonInterface $assignedAt, int $period = 0): Carbon
    {
        $assignedAt = Carbon::instance($assignedAt);
        if ($program->type === 'ANNUAL') {
            $yearEnd = Carbon::create($period ?: $assignedAt->year, 12, 31)->endOfDay();
            // Penugasan di tengah tahun: 31 Des atau +90 hari, mana yang lebih lambat (asumsi Q-03)
            $late = $assignedAt->copy()->addDays((int) Settings::get('annual_late_join_days', 90))->endOfDay();

            return $late->gt($yearEnd) ? $late : $yearEnd;
        }
        $days = min((int) ($program->deadline_days ?: 365), (int) config('blj.defaults.max_deadline_days'));

        return $assignedAt->copy()->addDays($days)->endOfDay();
    }

    // ------------------------------------------------------------------
    // Pengecualian & perpanjangan (BR-16, US-14)
    // ------------------------------------------------------------------

    public function exempt(Assignment $a, string $type, string $reasonCategory, string $reason, ?CarbonInterface $newDue = null, ?int $evidenceFileId = null): Exemption
    {
        if (! in_array($type, ['EXTEND', 'EXEMPT'], true)) {
            throw new InvalidArgumentException('Tipe pengecualian tidak dikenal.');
        }
        if ($a->isDone()) {
            throw new InvalidArgumentException('Penugasan sudah selesai.');
        }
        if ($type === 'EXTEND') {
            $limit = $a->assigned_at->copy()->addDays((int) config('blj.defaults.max_deadline_days'))->endOfDay();
            if (! $newDue || Carbon::instance($newDue)->lte(now())) {
                throw new InvalidArgumentException('Tenggat baru harus setelah hari ini.');
            }
            if (Carbon::instance($newDue)->gt($limit) && $reasonCategory !== 'CUTI_PANJANG' && $reasonCategory !== 'SAKIT') {
                // Perpanjangan melewati batas 1 tahun hanya untuk cuti panjang/sakit
                throw new InvalidArgumentException('Tenggat baru melewati batas 1 tahun sejak penugasan. Gunakan alasan cuti panjang/sakit atau pengecualian.');
            }
        }

        return DB::transaction(function () use ($a, $type, $reasonCategory, $reason, $newDue, $evidenceFileId) {
            $before = $a->only(['status', 'due_at']);
            $ex = $a->exemptions()->create([
                'type' => $type,
                'old_due_at' => $a->due_at,
                'new_due_at' => $type === 'EXTEND' ? Carbon::instance($newDue)->endOfDay() : null,
                'reason_category' => $reasonCategory,
                'reason' => $reason,
                'evidence_file_id' => $evidenceFileId,
                'approved_by' => auth()->id(),
            ]);
            if ($type === 'EXTEND') {
                $a->due_at = Carbon::instance($newDue)->endOfDay();
                if ($a->status === Assignment::OVERDUE) {
                    $a->status = $a->started_at ? Assignment::IN_PROGRESS : Assignment::NOT_STARTED;
                }
            } else {
                $a->status = Assignment::EXEMPTED;
            }
            $a->save();
            // Rekomendasi teguran yang belum diproses dibatalkan otomatis
            $a->followup()->where('status', 'RECOMMENDED')->update(['status' => 'CANCELLED', 'notes' => 'Dibatalkan otomatis: ada pengecualian/perpanjangan.']);
            AuditLogger::log('assignment.'.strtolower($type), $a, $before, $a->only(['status', 'due_at']) + ['reason' => $reason]);

            return $ex;
        });
    }

    private function activeEmployees(): Collection
    {
        return Employee::with(['department', 'workLocation'])->where('is_active', true)->get();
    }
}
