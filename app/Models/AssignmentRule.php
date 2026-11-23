<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentRule extends Model
{
    /** Atribut karyawan yang dapat dipakai sebagai kondisi (US-11 AC1). */
    public const FIELDS = [
        'position_id' => 'Jabatan',
        'grade_id' => 'Golongan',
        'department_id' => 'Departemen',
        'division_id' => 'Divisi',
        'work_location_id' => 'Lokasi kerja',
        'location_type' => 'Tipe lokasi (HO/Plant/Site)',
        'employment_status' => 'Status karyawan',
    ];

    protected $guarded = ['id'];

    protected $casts = ['conditions' => 'array', 'effective_from' => 'date', 'is_active' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }

    public function assignments() { return $this->hasMany(Assignment::class, 'rule_id'); }
}
