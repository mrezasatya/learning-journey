<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $guarded = ['id'];

    public function program() { return $this->belongsTo(Program::class); }

    public function lessons() { return $this->hasMany(Lesson::class)->orderBy('sort_order')->orderBy('id'); }
}
