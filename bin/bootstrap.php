<?php

declare(strict_types=1);

// Shared CLI bootstrap. Refuses to run through the web server.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

return App\Core\App::boot($root);
