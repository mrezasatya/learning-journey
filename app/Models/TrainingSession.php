<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    public function program() { return $this->belongsTo(Program::class); }

    public function participants() { return $this->hasMany(SessionParticipant::class, 'session_id'); }

    /** QR berlaku pada rentang sesi ± toleransi (US-23 AC1). */
    public function isCheckinOpen(): bool
    {
        $tol = (int) config('blj.defaults.qr_session_tolerance_minutes');

        return now()->between($this->start_at->copy()->subMinutes($tol), $this->end_at->copy()->addMinutes($tol));
    }
}
