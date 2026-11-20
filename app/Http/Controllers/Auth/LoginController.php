<?php

namespace App\Http\Controllers\Auth;

use App\Adapters\Auth\AuthAdapter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function show()
    {
        return Auth::check() ? redirect()->route('home') : view('auth.login');
    }

    public function login(Request $request, AuthAdapter $auth)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'NIK']);

        // Rate limiting per IP (Brief 9.2) — melengkapi penguncian per akun (BR-02)
        $key = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            return back()->withErrors(['username' => 'Terlalu banyak percobaan dari perangkat ini. Coba lagi beberapa menit lagi.'])->onlyInput('username');
        }
        RateLimiter::hit($key, 60);

        $result = $auth->attempt($data['username'], $data['password'], $request->boolean('remember'));
        if (! $result->success) {
            return back()->withErrors(['username' => $result->message])->onlyInput('username');
        }

        $request->session()->regenerate();

        if ($result->user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
