<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['answers' => 'array', 'submitted_at' => 'datetime'];

    public function assignment() { return $this->belongsTo(Assignment::class); }

    public function template() { return $this->belongsTo(SurveyTemplate::class, 'template_id'); }
}
