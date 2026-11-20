<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Ganti password (wajib saat login pertama — BR-01; kebijakan BR-02). */
class PasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('auth.change-password', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', self::rule()],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ], ['password' => 'password baru']);

        $user->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();

        return redirect()->route('home')->with('success', 'Password berhasil diganti.');
    }

    public static function rule(): Password
    {
        return Password::min((int) config('blj.auth.password_min_length'))->letters()->numbers();
    }
}
