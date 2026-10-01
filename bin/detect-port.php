#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Detect free ports for web server and Vite, then launch concurrently.
 *
 * Usage: php bin/detect-port.php
 * Called by: composer run dev
 */
function findFreePort(int $startPort, int $maxAttempts = 10): int
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $port = $startPort + $i;
        $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);

        if (! $conn) {
            return $port;
        }

        fclose($conn);
    }

    // Verify fallback port is actually available
    $fallbackPort = $startPort + $maxAttempts;
    $conn = @fsockopen('127.0.0.1', $fallbackPort, $errno, $errstr, 0.5);

    if ($conn) {
        fclose($conn);
        fwrite(STDERR, "Error: No free port found in range {$startPort}-{$fallbackPort}. Free a port and try again.\n");
        exit(1);
    }

    return $fallbackPort;
}

// Guard: only execute the launcher when run directly (not when required by tests)
if (realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $webPort = findFreePort(8000);
    $vitePort = findFreePort(5173);

    echo "\033[1mApp running at http://localhost:{$webPort}\033[0m".PHP_EOL;

    $command = implode(' ', [
        'npx concurrently',
        '-c "#93c5fd,#c4b5fd,#fdba74"',
        '"php artisan serve --port='.$webPort.'"',
        '"php artisan queue:listen --tries=1"',
        '"npm run dev -- --port='.$vitePort.'"',
        '--names="server,queue,vite"',
    ]);

    passthru($command, $exitCode);
    exit($exitCode);
}
