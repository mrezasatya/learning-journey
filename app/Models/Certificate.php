<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['issued_at' => 'datetime', 'valid_until' => 'date'];

    public function assignment() { return $this->belongsTo(Assignment::class); }

    public function file() { return $this->belongsTo(StoredFile::class, 'file_id'); }

    public function isValid(): bool
    {
        return $this->valid_until === null || $this->valid_until->endOfDay()->isFuture();
    }
}
