<?php

describe('Native Foundation Setup', function () {

    describe('APP_KEY conditional generation logic', function () {

        it('returns truthy when APP_KEY is already set to a base64 value', function () {
            $envContent = "APP_NAME=rekodo\nAPP_KEY=base64:abc123defGHI=\nAPP_DEBUG=false\n";

            $result = preg_match('/^APP_KEY=base64:/m', $envContent);

            expect($result)->toBe(1);
        });

        it('returns falsy when APP_KEY is empty', function () {
            $envContent = "APP_NAME=rekodo\nAPP_KEY=\nAPP_DEBUG=false\n";

            $result = preg_match('/^APP_KEY=base64:/m', $envContent);

            expect($result)->toBe(0);
        });

        it('returns falsy when env content is an empty string (no .env file)', function () {
            $envContent = '';

            $result = preg_match('/^APP_KEY=base64:/m', $envContent);

            expect($result)->toBe(0);
        });

    });

    describe('SQLite file touch idempotency', function () {

        it('touch creates a new file when it does not exist', function () {
            $tempPath = sys_get_temp_dir().'/test_sqlite_'.uniqid().'.sqlite';

            expect(file_exists($tempPath))->toBeFalse();

            touch($tempPath);

            expect(file_exists($tempPath))->toBeTrue();

            unlink($tempPath);
        });

        it('touch on an existing file leaves it intact and non-empty', function () {
            $tempPath = sys_get_temp_dir().'/test_sqlite_'.uniqid().'.sqlite';
            file_put_contents($tempPath, 'existing content');

            $contentBefore = file_get_contents($tempPath);
            touch($tempPath);
            $contentAfter = file_get_contents($tempPath);

            expect($contentAfter)->toBe($contentBefore);

            unlink($tempPath);
        });

    });

    describe('storage:link --force idempotency', function () {

        it('storage:link --force succeeds on first run', function () {
            $output = null;
            $exitCode = null;
            exec('php '.base_path('artisan').' storage:link --force 2>&1', $output, $exitCode);

            expect($exitCode)->toBe(0);
        });

        it('storage:link --force succeeds on second run when symlink already exists', function () {
            // First call ensures symlink exists
            exec('php '.base_path('artisan').' storage:link --force 2>&1');

            // Second call must also exit 0 (idempotent)
            $output = null;
            $exitCode = null;
            exec('php '.base_path('artisan').' storage:link --force 2>&1', $output, $exitCode);

            expect($exitCode)->toBe(0);
        });

    });

});
