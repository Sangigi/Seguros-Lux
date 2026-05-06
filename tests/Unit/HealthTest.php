<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Health;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testLivenessShape(): void
    {
        $s = Health::liveness();
        $this->assertTrue($s['ok']);
        $this->assertSame('crm', $s['service']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $s['time']);
    }
}
