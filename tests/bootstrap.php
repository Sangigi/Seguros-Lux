<?php

declare(strict_types=1);

putenv('APP_ENV=testing');

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
