<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CiFailurePropagationProbeTest extends TestCase
{
    public function test_a_required_backend_failure_fails_ci(): void
    {
        $this->fail('Intentional CI verification probe; removed after observing the hosted failure.');
    }
}
