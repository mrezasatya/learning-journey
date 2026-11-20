<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipicu setelah data karyawan bertambah/berubah (impor, edit, sinkronisasi ESS).
 * Pendengar di Tahap 5 mengevaluasi ulang aturan penugasan (BR-11, US-04 AC5).
 */
class EmployeesChanged
{
    use Dispatchable;

    /** @param array<int> $employeeIds */
    public function __construct(public array $employeeIds) {}
}
