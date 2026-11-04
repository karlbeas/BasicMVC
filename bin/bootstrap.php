<?php

declare(strict_types=1);

// Shared CLI bootstrap. Refuses to run through the web server.
// Command line only. Some hosts run cron jobs with a CGI binary: accept it when there is no HTTP request.
if (PHP_SAPI !== 'cli' && !(str_starts_with(PHP_SAPI, 'cgi') && !isset($_SERVER['REQUEST_METHOD']))) {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

return App\Core\App::boot($root);
