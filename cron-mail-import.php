<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// The import command owns the shared lock, including direct Artisan starts.
exit($app->handleCommand(new ArgvInput([__DIR__.'/artisan', 'calendar-mail:poll'])));
