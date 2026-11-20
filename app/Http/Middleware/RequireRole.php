<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;

/** RBAC di setiap route (Brief 9.2). Pemakaian: ->middleware('role:admin') */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        $allowed = false;
        foreach ($roles as $role) {
            $allowed = $allowed || match ($role) {
                'admin' => $user->isAdmin(),
                'superadmin' => $user->isSuperAdmin(),
                'supervisor' => $user->isSupervisor(),
                'employee' => $user->hasRole(Role::EMPLOYEE),
                default => false,
            };
        }
        abort_unless($allowed, 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
