<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $guarded = ['id'];

    protected $casts = [
        'pages_viewed' => 'array',
        'started_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignment() { return $this->belongsTo(Assignment::class); }

    public function lesson() { return $this->belongsTo(Lesson::class); }
}
