<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Core/helpers.php';

final class AssetUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('ASSET_PREFIX');
        parent::tearDown();
    }

    public function testWithoutPrefix(): void
    {
        putenv('ASSET_PREFIX=');
        $this->assertSame('/js/x.js', asset_url('js/x.js'));
        $this->assertSame('/js/x.js', asset_url('/js/x.js'));
    }

    public function testWithPrefix(): void
    {
        putenv('ASSET_PREFIX=/public');
        $this->assertSame('/public/js/notificaciones-navbar.js', asset_url('js/notificaciones-navbar.js'));
    }
}
