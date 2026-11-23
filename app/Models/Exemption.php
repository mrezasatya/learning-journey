<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exemption extends Model
{
    public const REASONS = [
        'CUTI_PANJANG' => 'Cuti panjang', 'SAKIT' => 'Sakit', 'TUGAS_KHUSUS' => 'Tugas khusus',
        'MUTASI' => 'Mutasi', 'LAINNYA' => 'Lainnya',
    ];

    protected $guarded = ['id'];

    protected $casts = ['old_due_at' => 'datetime', 'new_due_at' => 'datetime'];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function evidence() { return $this->belongsTo(StoredFile::class, 'evidence_file_id'); }
}
