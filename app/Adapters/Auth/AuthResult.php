<?php

namespace App\Adapters\Auth;

use App\Models\User;

final class AuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?User $user = null,
        public readonly ?string $message = null,
    ) {}

    public static function ok(User $user): self { return new self(true, $user); }

    public static function fail(string $message): self { return new self(false, null, $message); }
}
