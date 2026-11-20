<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persiapan fase kompetensi (Brief 7.7) — belum dipakai di fase 1. */
class Competency extends Model
{
    protected $guarded = ['id'];

    public function levels() { return $this->hasMany(CompetencyLevel::class); }
}
