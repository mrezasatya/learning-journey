<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Mengarahkan ke Beranda sesuai peran (US-01 AC1). */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin() && Route::has('admin.dashboard') && ! $request->session()->pull('prefer_employee_home')) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->employee) {
            return redirect()->route('employee.home');
        }

        return redirect()->route('admin.users.index');
    }
}
