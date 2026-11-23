<?php

namespace App\Models;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPassword
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // bcrypt (Brief 9.2)
            'must_change_password' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    private ?array $roleCodesCache = null;

    private ?bool $supervisorCache = null;

    public function employee() { return $this->belongsTo(Employee::class); }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    /** Kode peran yang disimpan (SUPER_ADMIN / ADMIN_LD) + EMPLOYEE & SUPERVISOR yang dihitung. */
    public function roleCodes(): array
    {
        if ($this->roleCodesCache === null) {
            $codes = $this->roles()->pluck('code')->all();
            if ($this->employee_id) {
                $codes[] = Role::EMPLOYEE;
            }
            if ($this->isSupervisor()) {
                $codes[] = Role::SUPERVISOR;
            }
            $this->roleCodesCache = array_values(array_unique($codes));
        }

        return $this->roleCodesCache;
    }

    public function hasRole(string ...$codes): bool
    {
        return (bool) array_intersect($codes, $this->roleCodes());
    }

    public function isSuperAdmin(): bool { return $this->hasRole(Role::SUPER_ADMIN); }

    public function isAdmin(): bool { return $this->hasRole(Role::ADMIN_LD, Role::SUPER_ADMIN); }

    /** Peran Atasan aktif otomatis bila NIK tercatat sebagai atasan ≥ 1 karyawan aktif (Brief Bagian 3). */
    public function isSupervisor(): bool
    {
        if ($this->supervisorCache === null) {
            $this->supervisorCache = $this->employee
                && Employee::where('supervisor_nik', $this->employee->nik)->where('is_active', true)->exists();
        }

        return $this->supervisorCache;
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->username;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->employee?->email;
    }

    public function displayName(): string
    {
        return $this->employee?->full_name ?? $this->name;
    }

    public function unreadNotificationCount(): int
    {
        return $this->appNotifications()->whereNull('read_at')->where('channel', 'IN_APP')->count();
    }
}
