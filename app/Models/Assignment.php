<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    public const NOT_STARTED = 'NOT_STARTED';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const COMPLETED = 'COMPLETED';
    public const COMPLETED_LATE = 'COMPLETED_LATE';
    public const OVERDUE = 'OVERDUE';
    public const EXEMPTED = 'EXEMPTED';

    public const STATUSES = [
        self::NOT_STARTED => 'Belum Mulai',
        self::IN_PROGRESS => 'Sedang Berjalan',
        self::COMPLETED => 'Selesai',
        self::COMPLETED_LATE => 'Selesai (Terlambat)',
        self::OVERDUE => 'Overdue',
        self::EXEMPTED => 'Dikecualikan',
    ];

    public const OPEN_STATUSES = [self::NOT_STARTED, self::IN_PROGRESS, self::OVERDUE];
    public const DONE_STATUSES = [self::COMPLETED, self::COMPLETED_LATE];

    protected $guarded = ['id'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function program() { return $this->belongsTo(Program::class); }
    public function rule() { return $this->belongsTo(AssignmentRule::class, 'rule_id'); }
    public function lessonProgress() { return $this->hasMany(LessonProgress::class); }
    public function attempts() { return $this->hasMany(AssessmentAttempt::class)->orderBy('id'); }
    public function surveyResponse() { return $this->hasOne(SurveyResponse::class); }
    public function certificate() { return $this->hasOne(Certificate::class); }
    public function exemptions() { return $this->hasMany(Exemption::class)->latest(); }
    public function manualCompletion() { return $this->hasOne(ManualCompletion::class); }
    public function followup() { return $this->hasOne(DisciplinaryFollowup::class); }
    public function reminderLogs() { return $this->hasMany(ReminderLog::class); }

    public function scopeOpen($q) { return $q->whereIn('assignments.status', self::OPEN_STATUSES); }
    public function scopeDone($q) { return $q->whereIn('assignments.status', self::DONE_STATUSES); }

    public function isDone(): bool { return in_array($this->status, self::DONE_STATUSES, true); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN_STATUSES, true); }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function statusBadge(): string
    {
        return match ($this->status) {
            self::COMPLETED => 'badge-success',
            self::COMPLETED_LATE => 'badge-success',
            self::OVERDUE => 'badge-danger',
            self::IN_PROGRESS => 'badge-info',
            self::EXEMPTED => 'badge-neutral',
            default => $this->daysLeft() !== null && $this->daysLeft() <= 30 ? 'badge-warning' : 'badge-neutral',
        };
    }

    /** Sisa hari menuju tenggat (negatif = lewat). Null bila sudah selesai/dikecualikan. */
    public function daysLeft(): ?int
    {
        if (! $this->isOpen()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_at->copy()->startOfDay(), false);
    }

    public function periodLabel(): string
    {
        return $this->period_year ? (string) $this->period_year : '—';
    }
}
