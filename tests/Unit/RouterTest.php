<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

// Router.php lives at <project-root>/Core/Router.php.
// This file is at tests/Unit/RouterTest.php  →  root is dirname(__DIR__, 2).
require_once dirname(__DIR__, 2) . '/Core/Router.php';

final class RouterTest extends TestCase
{
    public function testNormalizePathReplacesMixedSeparators(): void
    {
        $input = 'foo/bar\\baz';
        $out   = \Router::normalizePath($input);
        $this->assertSame(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input),
            $out
        );
    }
}