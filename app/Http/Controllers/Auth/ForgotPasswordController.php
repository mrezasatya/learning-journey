<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Reset password via email: tautan berlaku 30 menit & sekali pakai (BR-03, US-02). */
class ForgotPasswordController extends Controller
{
    private const TTL_MINUTES = 30;

    public function request()
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $request->validate(['username' => ['required', 'string', 'max:50']], [], ['username' => 'NIK']);
        $user = User::with('employee')->where('username', $request->username)->first();

        if ($user && ! $user->employee?->email) {
            return back()->with('info', 'NIK ini tidak memiliki email terdaftar. Silakan hubungi Admin L&D untuk reset password.');
        }

        if ($user && $user->employee?->is_active) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->username],
                ['token' => Hash::make($token), 'created_at' => now()]
            );
            $url = route('password.reset', ['token' => $token, 'nik' => $user->username]);
            Mail::raw(
                "Halo {$user->displayName()},\n\nGunakan tautan berikut untuk membuat password baru (berlaku ".self::TTL_MINUTES." menit, sekali pakai):\n{$url}\n\nAbaikan email ini bila Anda tidak meminta reset password.\n\nBukaka Learning Journey",
                fn ($m) => $m->to($user->employee->email)->subject('Reset password BLJ')
            );
        }

        // Pesan sama untuk NIK ada/tidak ada (mencegah penebakan NIK)
        return back()->with('success', 'Bila NIK terdaftar dan memiliki email, tautan reset telah dikirim. Periksa email Anda.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'nik' => $request->query('nik')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => 'required', 'username' => 'required',
            'password' => ['required', 'confirmed', PasswordController::rule()],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $request->username)->first();
        $valid = $row && Hash::check($request->token, $row->token)
            && now()->diffInMinutes($row->created_at, true) <= self::TTL_MINUTES;
        if (! $valid) {
            return back()->withErrors(['password' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.']);
        }

        $user = User::where('username', $request->username)->firstOrFail();
        $user->forceFill([
            'password' => Hash::make($request->password), 'must_change_password' => false,
            'failed_attempts' => 0, 'locked_until' => null,
        ])->save();
        DB::table('password_reset_tokens')->where('email', $request->username)->delete(); // sekali pakai

        return redirect()->route('login')->with('success', 'Password berhasil dibuat. Silakan login.');
    }
}
