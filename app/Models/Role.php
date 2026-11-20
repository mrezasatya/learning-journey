<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN_LD = 'ADMIN_LD';
    public const SUPERVISOR = 'SUPERVISOR'; // dihitung otomatis, tidak disimpan di user_roles
    public const EMPLOYEE = 'EMPLOYEE';

    protected $guarded = ['id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles')->withTimestamps();
    }
}
