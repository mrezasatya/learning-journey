<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    protected function actingAsNik(string $username): static
    {
        return $this->actingAs($this->user($username));
    }
}
