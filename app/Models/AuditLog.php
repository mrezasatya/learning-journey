<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Audit log bersifat append-only: tidak dapat diubah atau dihapus dari aplikasi (BR-47). */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
