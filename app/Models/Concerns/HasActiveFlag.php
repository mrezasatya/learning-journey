<?php

namespace App\Models\Concerns;

/** Data master: tidak dihapus bila sudah dipakai, cukup dinonaktifkan (US-05 AC1). */
trait HasActiveFlag
{
    public function scopeActive($query)
    {
        return $query->where($this->getTable().'.is_active', true);
    }
}
