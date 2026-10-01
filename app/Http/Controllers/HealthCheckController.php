<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Database connectivity timeout in seconds (D-05).
     */
    private const DB_TIMEOUT_SECONDS = 3;

    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';
        $dbLatency = null;

        $previousTimeout = ini_get('default_socket_timeout');

        try {
            ini_set('default_socket_timeout', self::DB_TIMEOUT_SECONDS);

            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = (int) round((microtime(true) - $start) * 1000);
        } catch (\Throwable $e) {
            $dbStatus = 'error';
        } finally {
            ini_set('default_socket_timeout', $previousTimeout);
        }

        $status = $dbStatus === 'ok' ? 'ok' : 'error';
        $httpCode = $status === 'ok' ? 200 : 503;

        return response()->json([
            'status' => $status,
            'version' => config('app.version'),
            'php' => PHP_VERSION,
            'database' => [
                'status' => $dbStatus,
                'latency_ms' => $dbLatency,
            ],
        ], $httpCode);
    }
}
