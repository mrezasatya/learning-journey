<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttemptAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['option_order' => 'array', 'selected_option_ids' => 'array', 'is_correct' => 'boolean'];

    public function attempt() { return $this->belongsTo(AssessmentAttempt::class, 'attempt_id'); }

    public function question() { return $this->belongsTo(Question::class); }
}
