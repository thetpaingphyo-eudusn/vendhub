<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionDirectory = sys_get_temp_dir() . '/vendhub-test-sessions';
    if (!is_dir($sessionDirectory)) {
        mkdir($sessionDirectory, 0700, true);
    }
    session_save_path($sessionDirectory);
    session_start();
}

$_SESSION['csrf'] = $_SESSION['csrf'] ?? 'test-csrf-token';
