<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionParticipant extends Model
{
    public const STATUSES = ['INVITED' => 'Diundang', 'PRESENT' => 'Hadir', 'ABSENT' => 'Tidak hadir'];

    protected $guarded = ['id'];

    protected $casts = ['checkin_at' => 'datetime'];

    public function session() { return $this->belongsTo(TrainingSession::class, 'session_id'); }

    public function employee() { return $this->belongsTo(Employee::class); }
}
