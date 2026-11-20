<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Mencatat seluruh aksi admin ke audit log yang tidak dapat diubah (BR-47, US-29). */
class AuditLogger
{
    public static function log(string $action, Model|string|null $entity = null, ?array $before = null, ?array $after = null, ?int $entityId = null): AuditLog
    {
        $hidden = ['password', 'remember_token'];
        $clean = fn (?array $a) => $a === null ? null : array_diff_key($a, array_flip($hidden));

        return AuditLog::create([
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'entity' => $entity instanceof Model ? class_basename($entity) : $entity,
            'entity_id' => $entity instanceof Model ? $entity->getKey() : $entityId,
            'before' => $clean($before),
            'after' => $clean($after),
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Pencatatan perubahan model: hanya kolom yang berubah. */
    public static function changes(string $action, Model $model, array $original): AuditLog
    {
        $changed = $model->getChanges();
        unset($changed['updated_at']);
        $before = array_intersect_key($original, $changed);

        return static::log($action, $model, $before, $changed);
    }
}
