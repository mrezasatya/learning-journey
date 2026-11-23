<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    protected $guarded = ['id'];

    public function program() { return $this->belongsTo(Program::class); }

    public function questions() { return $this->hasMany(Question::class, 'bank_id'); }
}
