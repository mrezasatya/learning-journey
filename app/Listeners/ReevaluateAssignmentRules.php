<?php

namespace App\Listeners;

use App\Events\EmployeesChanged;
use App\Services\AssignmentEngine;

/** Karyawan baru/berubah otomatis mendapat program sesuai aturan (BR-11, US-04 AC5). */
class ReevaluateAssignmentRules
{
    public function __construct(private AssignmentEngine $engine) {}

    public function handle(EmployeesChanged $event): void
    {
        $this->engine->syncEmployees($event->employeeIds);
    }
}
