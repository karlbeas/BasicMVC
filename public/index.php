<?php

declare(strict_types=1);

/*
 * F2S Pro front controller.
 * If public/ had to be copied into public_html while the rest of the app lives
 * elsewhere, create public_html/f2s-path.php returning the app root, e.g.
 *   <?php return '/home/USER/f2s';
 */
// Local dev server (php -S … -t public public/index.php): let it serve static files.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

$root = is_file(__DIR__ . '/f2s-path.php') ? (string) require __DIR__ . '/f2s-path.php' : dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    echo 'F2S Pro: dependencies missing (vendor/). Deploy a release built with "composer install --no-dev".';
    exit;
}
require $root . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Request;

$app = App::boot($root);
$app->setBasePath(App::detectBasePath($_SERVER, $app->config()->string('app.url')));
$app->handle(Request::fromGlobals($app->basePath()))->send();
