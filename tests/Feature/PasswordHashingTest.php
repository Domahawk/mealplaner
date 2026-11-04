<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordHashingTest extends TestCase
{
    public function test_the_configured_native_hasher_verifies_passwords_without_storing_plaintext(): void
    {
        $password = 'audit-only-password-fixture';
        $hash = Hash::make($password);

        $this->assertNotSame($password, $hash);
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertFalse(Hash::check('different-audit-fixture', $hash));
    }
}
