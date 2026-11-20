<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCompletion extends Model
{
    protected $guarded = ['id'];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function evidence() { return $this->belongsTo(StoredFile::class, 'evidence_file_id'); }
}
