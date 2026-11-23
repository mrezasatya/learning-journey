<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    public const TYPES = ['ONBOARDING' => 'Onboarding', 'ONCE' => 'Wajib Sekali', 'ANNUAL' => 'Wajib Tahunan'];
    public const CATEGORIES = ['ISO' => 'ISO', 'K3' => 'K3', 'ONBOARDING' => 'Onboarding', 'OTHER' => 'Lainnya'];
    public const STATUSES = ['DRAFT' => 'Draft', 'PUBLISHED' => 'Terbit', 'ARCHIVED' => 'Diarsipkan'];

    protected $guarded = ['id'];

    protected $casts = [
        'sequential' => 'boolean',
        'is_blended' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function modules() { return $this->hasMany(Module::class)->orderBy('sort_order')->orderBy('id'); }
    public function lessons() { return $this->hasManyThrough(Lesson::class, Module::class); }
    public function questionBank() { return $this->hasOne(QuestionBank::class); }
    public function assessments() { return $this->hasMany(Assessment::class); }
    public function preAssessment() { return $this->hasOne(Assessment::class)->where('kind', 'PRE'); }
    public function postAssessment() { return $this->hasOne(Assessment::class)->where('kind', 'POST'); }
    public function rules() { return $this->hasMany(AssignmentRule::class); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function surveyTemplate() { return $this->belongsTo(SurveyTemplate::class); }
    public function thumbnail() { return $this->belongsTo(StoredFile::class, 'thumbnail_file_id'); }
    public function parent() { return $this->belongsTo(Program::class, 'parent_program_id'); }
    public function trainingSessions() { return $this->hasMany(TrainingSession::class); }

    public function scopePublished($q) { return $q->where('status', 'PUBLISHED'); }

    public function isPublished(): bool { return $this->status === 'PUBLISHED'; }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }

    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    /** Daftar materi berurutan (modul → materi). */
    public function orderedLessons()
    {
        return Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('modules.program_id', $this->id)
            ->orderBy('modules.sort_order')->orderBy('modules.id')
            ->orderBy('lessons.sort_order')->orderBy('lessons.id')
            ->select('lessons.*')
            ->get();
    }

    /** Template survei: milik program, atau default (BR-31). */
    public function effectiveSurveyTemplate(): ?SurveyTemplate
    {
        return $this->surveyTemplate ?? SurveyTemplate::default();
    }
}
