<?php

namespace App\Events;

use App\Models\Assignment;
use Illuminate\Foundation\Events\Dispatchable;

/** Dipicu saat karyawan mendapat penugasan baru (dipakai notifikasi "saat ditugaskan" — BR-38). */
class AssignmentCreated
{
    use Dispatchable;

    public function __construct(public Assignment $assignment) {}
}
