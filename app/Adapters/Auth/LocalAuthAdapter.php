<?php

namespace App\Adapters\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/** Login NIK + password dengan penguncian akun (BR-01, BR-02, BR-05). */
class LocalAuthAdapter implements AuthAdapter
{
    private const GENERIC = 'NIK atau password salah.';

    public function attempt(string $username, string $password, bool $remember = false): AuthResult
    {
        $user = User::with('employee')->where('username', trim($username))->first();
        if (! $user) {
            Hash::check($password, '$2y$12$abcdefghijklmnopqrstuuJ8m6lQx2k3t5Y0u2mWk3uY1C7cQ9yqG'); // waktu respons seragam

            return AuthResult::fail(self::GENERIC);
        }

        if ($user->isLocked()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->locked_until) / 60);

            return AuthResult::fail("Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$minutes} menit atau hubungi Admin L&D.");
        }

        if (! Hash::check($password, $user->password)) {
            $max = (int) config('blj.auth.max_failed_attempts');
            $user->failed_attempts++;
            if ($user->failed_attempts >= $max) {
                $user->failed_attempts = 0;
                $user->locked_until = now()->addMinutes((int) config('blj.auth.lockout_minutes'));
                $user->save();

                return AuthResult::fail('Akun terkunci 15 menit karena 5 kali gagal login. Hubungi Admin L&D bila perlu dibuka lebih cepat.');
            }
            $user->save();
            $left = $max - $user->failed_attempts;

            return AuthResult::fail(self::GENERIC." Sisa percobaan sebelum akun terkunci: {$left}.");
        }

        if ($user->employee && ! $user->employee->is_active) {
            return AuthResult::fail('Akun tidak aktif, hubungi Admin L&D.');
        }

        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();
        Auth::login($user, $remember);

        return AuthResult::ok($user);
    }
}
