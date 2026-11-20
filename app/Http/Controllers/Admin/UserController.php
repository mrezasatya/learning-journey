<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Akun & password: reset, buka kunci (US-03), kelola peran admin (Super Admin). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $users = User::with(['employee.position', 'roles'])
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('username', 'like', "%$q%")->orWhere('name', 'like', "%$q%")))
            ->when($request->query('filter') === 'locked', fn ($w) => $w->where('locked_until', '>', now()))
            ->when($request->query('filter') === 'admin', fn ($w) => $w->whereHas('roles'))
            ->orderBy('username')->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function resetPassword(User $user)
    {
        $temp = 'Blj'.random_int(10000, 99999).Str::lower(Str::random(2));
        $user->forceFill([
            'password' => Hash::make($temp), 'must_change_password' => true,
            'failed_attempts' => 0, 'locked_until' => null,
        ])->save();
        AuditLogger::log('user.reset_password', $user, null, ['username' => $user->username]);

        return back()->with('success', "Password sementara untuk {$user->username} ({$user->displayName()}): {$temp}\nSampaikan secara langsung/pribadi. Karyawan wajib menggantinya saat login.");
    }

    public function unlock(User $user)
    {
        $before = ['locked_until' => $user->locked_until?->toDateTimeString()];
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
        AuditLogger::log('user.unlock', $user, $before, ['locked_until' => null]);

        return back()->with('success', "Akun {$user->username} sudah dibuka.");
    }

    public function updateRoles(Request $request, User $user)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $codes = array_intersect((array) $request->input('roles', []), [Role::ADMIN_LD, Role::SUPER_ADMIN]);
        if ($user->is($request->user()) && ! in_array(Role::SUPER_ADMIN, $codes, true)) {
            return back()->withErrors(['roles' => 'Anda tidak dapat mencabut peran Super Admin dari akun sendiri.']);
        }
        $before = $user->roles()->pluck('code')->all();
        $user->roles()->sync(Role::whereIn('code', $codes)->pluck('id'));
        AuditLogger::log('user.roles', $user, ['roles' => $before], ['roles' => array_values($codes)]);

        return back()->with('success', "Peran {$user->username} diperbarui.");
    }
}
