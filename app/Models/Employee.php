<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    public const STATUSES = ['TETAP' => 'Tetap', 'KONTRAK' => 'Kontrak', 'PROYEK' => 'Proyek'];

    protected $guarded = ['id'];

    protected $casts = [
        'join_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function position() { return $this->belongsTo(Position::class); }
    public function grade() { return $this->belongsTo(Grade::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function workLocation() { return $this->belongsTo(WorkLocation::class); }
    public function user() { return $this->hasOne(User::class); }
    public function assignments() { return $this->hasMany(Assignment::class); }

    /** Atasan langsung (dicocokkan lewat NIK). */
    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_nik', 'nik');
    }

    /** Anggota tim langsung. */
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'supervisor_nik', 'nik');
    }

    public function scopeActive($q)
    {
        return $q->where('employees.is_active', true);
    }

    /** Divisi diturunkan dari departemen (Brief 7.1: departments.division_id). */
    public function getDivisionIdAttribute(): ?int
    {
        return $this->department?->division_id;
    }

    /**
     * NIK seluruh bawahan tidak langsung (rekursif) — untuk tampilan Dept./Division Head (Q-05).
     *
     * @return array<string>
     */
    public function allSubordinateNiks(int $maxDepth = 6): array
    {
        $result = [];
        $frontier = [$this->nik];
        for ($depth = 0; $depth < $maxDepth && $frontier; $depth++) {
            $next = Employee::whereIn('supervisor_nik', $frontier)->where('is_active', true)->pluck('nik')->all();
            $next = array_values(array_diff($next, $result, [$this->nik]));
            $result = array_merge($result, $next);
            $frontier = $next;
        }

        return $result;
    }
}
