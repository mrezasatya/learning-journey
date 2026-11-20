<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['difficulty_mix' => 'array', 'show_explanation' => 'boolean'];

    public function program() { return $this->belongsTo(Program::class); }

    public function attempts() { return $this->hasMany(AssessmentAttempt::class); }

    public function label(): string { return $this->kind === 'PRE' ? 'Pre-test' : 'Post-test'; }
}
