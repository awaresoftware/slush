<?php

use Illuminate\Support\Facades\DB;

describe('HLTH-01 (/healthz endpoint)', function () {

    it('GET /healthz returns 200 when database is reachable', function () {
        $this->getJson('/healthz')
            ->assertStatus(200);
    });

    it('GET /healthz response JSON contains all required keys', function () {
        $this->getJson('/healthz')
            ->assertJsonStructure([
                'status',
                'version',
                'php',
                'database' => [
                    'status',
                    'latency_ms',
                ],
            ]);
    });

    it('GET /healthz returns status ok and database.status ok when database is healthy', function () {
        $this->getJson('/healthz')
            ->assertJson([
                'status' => 'ok',
                'database' => [
                    'status' => 'ok',
                ],
            ]);
    });

    it('GET /healthz returns database.latency_ms as an integer when database is healthy', function () {
        $response = $this->getJson('/healthz');

        $data = $response->json();

        expect($data['database']['latency_ms'])->toBeInt();
    });

    it('GET /healthz returns the value of config app.version in the version field', function () {
        $this->getJson('/healthz')
            ->assertJson([
                'version' => config('app.version'),
            ]);
    });

    it('GET /healthz returns PHP_VERSION in the php field', function () {
        $this->getJson('/healthz')
            ->assertJson([
                'php' => PHP_VERSION,
            ]);
    });

    it('GET /healthz is accessible without authentication', function () {
        $this->getJson('/healthz')
            ->assertStatus(200);
    });

    it('GET /healthz returns 503 when database connection fails', function () {
        DB::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        $this->getJson('/healthz')
            ->assertStatus(503);
    });

    it('GET /healthz returns status error and database.latency_ms null when database fails', function () {
        DB::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

        $this->getJson('/healthz')
            ->assertJson([
                'status' => 'error',
                'database' => [
                    'status' => 'error',
                    'latency_ms' => null,
                ],
            ]);
    });

    it('HealthCheckController source contains default_socket_timeout proving the 3-second DB timeout is configured per D-05', function () {
        $source = file_get_contents(app_path('Http/Controllers/HealthCheckController.php'));

        expect($source)
            ->toContain('default_socket_timeout')
            ->and($source)->toContain('3');
    });

});
