<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Resolve the project root directory for file-based assertions.
 * Uses __DIR__ relative navigation to avoid relying on base_path(),
 * which can resolve to the wrong directory in git worktree environments.
 */
function dockerTestProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

describe('DOCK-01 (Services)', function () {

    it('compose.yaml exists at project root', function () {
        expect(file_exists(dockerTestProjectRoot().'/compose.yaml'))->toBeTrue();
    });

    it('compose.yaml contains laravel.test service', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');

        expect($config['services'])->toHaveKey('laravel.test');
    });

    it('compose.yaml contains mysql service', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');

        expect($config['services'])->toHaveKey('mysql');
    });

    it('compose.yaml contains redis service', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');

        expect($config['services'])->toHaveKey('redis');
    });

    it('compose.yaml contains worker service', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');

        expect($config['services'])->toHaveKey('worker');
    });

    it('laravel.test build context points to sail runtimes 8.4', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $context = $config['services']['laravel.test']['build']['context'];

        expect($context)->toContain('sail/runtimes/8.4');
    });

    it('worker command contains queue:work', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $command = $config['services']['worker']['command'];

        $commandStr = is_array($command) ? implode(' ', $command) : $command;

        expect($commandStr)->toContain('queue:work');
    });

});

describe('DOCK-02 (Env File)', function () {

    it('.env.docker.example exists', function () {
        expect(file_exists(dockerTestProjectRoot().'/.env.docker.example'))->toBeTrue();
    });

    it('.env.docker.example contains DB_CONNECTION=mysql', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/.env.docker.example');

        expect($content)->toContain('DB_CONNECTION=mysql');
    });

    it('.env.docker.example contains QUEUE_CONNECTION=redis', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/.env.docker.example');

        expect($content)->toContain('QUEUE_CONNECTION=redis');
    });

    it('.env.docker.example contains CACHE_STORE=redis', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/.env.docker.example');

        expect($content)->toContain('CACHE_STORE=redis');
    });

    it('.env.docker.example contains WWWUSER=1000', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/.env.docker.example');

        expect($content)->toContain('WWWUSER=1000');
    });

    it('.env.docker.example contains WWWGROUP=1000', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/.env.docker.example');

        expect($content)->toContain('WWWGROUP=1000');
    });

});

describe('DOCK-03 (Music Override)', function () {

    it('compose.music.yaml exists', function () {
        expect(file_exists(dockerTestProjectRoot().'/compose.music.yaml'))->toBeTrue();
    });

    it('compose.music.yaml contains MUSIC_LIBRARY_PATH volume mount', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/compose.music.yaml');

        expect($content)->toContain('{MUSIC_LIBRARY_PATH}:/music');
    });

});

describe('DOCK-04 (Healthchecks)', function () {

    it('mysql healthcheck uses mysqladmin ping', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $healthcheck = $config['services']['mysql']['healthcheck']['test'];

        $healthcheckStr = is_array($healthcheck) ? implode(' ', $healthcheck) : $healthcheck;

        expect($healthcheckStr)->toContain('mysqladmin');
    });

    it('redis healthcheck uses redis-cli ping', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $healthcheck = $config['services']['redis']['healthcheck']['test'];

        $healthcheckStr = is_array($healthcheck) ? implode(' ', $healthcheck) : $healthcheck;

        expect($healthcheckStr)->toContain('redis-cli');
    });

    it('laravel.test depends_on mysql with service_healthy', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $condition = $config['services']['laravel.test']['depends_on']['mysql']['condition'];

        expect($condition)->toBe('service_healthy');
    });

    it('laravel.test depends_on redis with service_healthy', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $condition = $config['services']['laravel.test']['depends_on']['redis']['condition'];

        expect($condition)->toBe('service_healthy');
    });

    it('worker depends_on mysql with service_healthy', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $condition = $config['services']['worker']['depends_on']['mysql']['condition'];

        expect($condition)->toBe('service_healthy');
    });

    it('worker depends_on redis with service_healthy', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $condition = $config['services']['worker']['depends_on']['redis']['condition'];

        expect($condition)->toBe('service_healthy');
    });

    it('mysql healthcheck has start_period of 30s', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $startPeriod = $config['services']['mysql']['healthcheck']['start_period'];

        expect($startPeriod)->toBe('30s');
    });

});

describe('HLTH-01 (Web Healthcheck)', function () {

    it('laravel.test has a healthcheck block', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');

        expect($config['services']['laravel.test'])->toHaveKey('healthcheck');
    });

    it('laravel.test healthcheck curls /healthz', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $test = $config['services']['laravel.test']['healthcheck']['test'];

        $testStr = is_array($test) ? implode(' ', $test) : $test;

        expect($testStr)->toContain('curl')
            ->and($testStr)->toContain('/healthz');
    });

    it('laravel.test healthcheck has start_period of 60s', function () {
        $config = Yaml::parseFile(dockerTestProjectRoot().'/compose.yaml');
        $startPeriod = $config['services']['laravel.test']['healthcheck']['start_period'];

        expect($startPeriod)->toBe('60s');
    });

});

describe('Entrypoint (docker/start.sh)', function () {

    it('docker/start.sh exists', function () {
        expect(file_exists(dockerTestProjectRoot().'/docker/start.sh'))->toBeTrue();
    });

    it('docker/start.sh contains artisan migrate --force', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/docker/start.sh');

        expect($content)->toContain('php artisan migrate --force');
    });

    it('docker/start.sh checks for public/build/manifest.json before building', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/docker/start.sh');

        expect($content)->toContain('public/build/manifest.json');
    });

    it('docker/start.sh runs npm run build for missing assets', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/docker/start.sh');

        expect($content)->toContain('npm run build');
    });

    it('docker/start.sh runs storage:link --force', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/docker/start.sh');

        expect($content)->toContain('php artisan storage:link --force');
    });

    it('docker/start.sh hands off to the web server directly', function () {
        $content = file_get_contents(dockerTestProjectRoot().'/docker/start.sh');

        // Sail's supervisord config is root-owned, which the sail user running
        // this script cannot exec, so it runs the server supervisord would.
        expect($content)->toContain('exec ${SUPERVISOR_PHP_COMMAND:-/usr/bin/php')
            ->and($content)->toContain('artisan serve');
    });

});
