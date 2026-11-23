<?php

namespace App\Http\Controllers\Employee;

use App\Models\Assignment;

trait Concerns
{
    /** Karyawan hanya boleh mengakses penugasannya sendiri (BR-48). */
    protected function own(Assignment $a): Assignment
    {
        abort_unless($a->employee_id === auth()->user()->employee_id, 403, 'Bukan penugasan Anda.');

        return $a->loadMissing('program.preAssessment', 'program.postAssessment', 'surveyResponse', 'certificate');
    }
}
