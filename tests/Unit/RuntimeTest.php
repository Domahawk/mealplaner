<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RuntimeTest extends TestCase
{
    public function test_php_runtime_supports_the_required_extensions(): void
    {
        foreach (['pdo_pgsql', 'mbstring', 'openssl', 'xml', 'ctype'] as $extension) {
            $this->assertTrue(extension_loaded($extension), "Missing PHP extension: {$extension}");
        }
    }
}
