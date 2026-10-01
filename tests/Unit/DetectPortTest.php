<?php

require_once __DIR__.'/../../bin/detect-port.php';

describe('findFreePort', function () {
    test('returns start port when it is free', function () {
        // Port 59123 is extremely unlikely to be in use
        $port = findFreePort(59123);
        expect($port)->toBe(59123);
    });

    test('returns a port within valid range', function () {
        $startPort = 59200;
        $maxAttempts = 10;
        $port = findFreePort($startPort, $maxAttempts);
        expect($port)->toBeGreaterThanOrEqual($startPort);
        expect($port)->toBeLessThanOrEqual($startPort + $maxAttempts);
    });

    test('with maxAttempts=1 returns startPort when free', function () {
        $port = findFreePort(59300, 1);
        expect($port)->toBe(59300);
    });
});
