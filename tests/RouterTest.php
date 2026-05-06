<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Core/Router.php';

final class RouterTest extends TestCase
{
    public function testNormalizePathReplacesMixedSeparators(): void
    {
        $input = 'foo/bar\\baz';
        $out = \Router::normalizePath($input);
        $this->assertSame(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input),
            $out
        );
    }
}
