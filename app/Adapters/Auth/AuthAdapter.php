<?php

namespace App\Adapters\Auth;

/**
 * Titik sambung autentikasi (BR-04). Fase 1 memakai LocalAuthAdapter (NIK + password).
 * Fase 5 cukup membuat SsoAuthAdapter yang mengimplementasikan interface ini,
 * lalu mengganti BLJ_AUTH_DRIVER=sso — modul lain tidak berubah.
 */
interface AuthAdapter
{
    public function attempt(string $username, string $password, bool $remember = false): AuthResult;
}
