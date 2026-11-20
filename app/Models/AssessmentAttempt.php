<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'submitted_at' => 'datetime',
        'passed' => 'boolean',
    ];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function answers() { return $this->hasMany(AttemptAnswer::class, 'attempt_id')->orderBy('sort_order'); }

    public function isOpen(): bool { return $this->submitted_at === null; }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }
}
