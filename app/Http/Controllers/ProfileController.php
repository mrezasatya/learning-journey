<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Profil (hanya baca, data dari master karyawan — layar 10). */
class ProfileController extends Controller
{
    public function __invoke(Request $request)
    {
        $employee = $request->user()->employee?->load('position', 'grade', 'department.division', 'workLocation', 'supervisor');
        abort_unless($employee, 404);

        return view('employee.profile', compact('employee'));
    }
}
