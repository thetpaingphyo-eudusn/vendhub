<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Dependencies are missing. Run composer install in the project root.';
    exit;
}

require $autoload;

$kernel = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel->handle()->send();
