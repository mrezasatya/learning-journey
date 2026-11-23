<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * - Karyawan yang dinonaktifkan saat sedang login langsung dikeluarkan (BR-05).
 * - Password awal/sementara wajib diganti sebelum memakai aplikasi (BR-01, US-03).
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->employee && ! $user->employee->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['username' => 'Akun tidak aktif, hubungi Admin L&D.']);
        }

        if ($user && $user->must_change_password && ! $request->routeIs('password.change*', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
