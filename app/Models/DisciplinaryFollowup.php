<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryFollowup extends Model
{
    public const STATUSES = [
        'RECOMMENDED' => 'Direkomendasikan', 'VERIFIED' => 'Terverifikasi',
        'ISSUED' => 'Teguran terbit', 'CANCELLED' => 'Dibatalkan',
    ];

    protected $guarded = ['id'];

    protected $casts = ['letter_date' => 'date'];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function document() { return $this->belongsTo(StoredFile::class, 'document_file_id'); }
}
