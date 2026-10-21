<?php
// The app waits for Postgres and web services with Tenma's own functions, which only run inside Tenma's event
// loop. vendor/bin/phpunit starts in plain PHP: then this hands the whole run to tests/Support/tenma.php under tenma
// (TENMA_BIN, or tenma from the PATH) and exits with its status. Under tenma it only loads the classes.

if (!class_exists(\Http\Server::class, false)) {
    $tenma = getenv('TENMA_BIN') ?: 'tenma';
    $arguments = array_map(escapeshellarg(...), array_slice(Waypoint\Support\Row::of($_SERVER)->list('argv', 'string'), 1));
    passthru(escapeshellarg($tenma) . ' ' . escapeshellarg(__DIR__ . '/tenma.php') . ' ' . implode(' ', $arguments), $status);
    exit($status);
}

require __DIR__ . '/../../vendor/autoload.php';
