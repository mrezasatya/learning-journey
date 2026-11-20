<?php

namespace App\Events;

use App\Models\Assignment;
use Illuminate\Foundation\Events\Dispatchable;

/** Gagal post-test setelah percobaan terakhir → perlu mengulang materi (BR-24). Atasan & Admin dinotifikasi. */
class PostTestExhausted
{
    use Dispatchable;

    public function __construct(public Assignment $assignment, public int $previousCycle) {}
}
