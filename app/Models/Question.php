<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    public const TYPES = ['SINGLE' => 'Pilihan ganda (1 jawaban)', 'MULTI' => 'Pilihan ganda kompleks (>1 jawaban)', 'TRUE_FALSE' => 'Benar/Salah'];
    public const DIFFICULTIES = ['EASY' => 'Mudah', 'MEDIUM' => 'Sedang', 'HARD' => 'Sulit'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function bank() { return $this->belongsTo(QuestionBank::class, 'bank_id'); }
    public function module() { return $this->belongsTo(Module::class); }
    public function options() { return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id'); }

    /** @return array<int> */
    public function correctOptionIds(): array
    {
        return $this->options->where('is_correct', true)->pluck('id')->map(fn ($i) => (int) $i)->sort()->values()->all();
    }
}
