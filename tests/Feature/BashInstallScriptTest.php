<?php

/**
 * Resolve the project root directory for file-based assertions.
 * Uses __DIR__ relative navigation to avoid relying on base_path(),
 * which can resolve to the wrong directory in git worktree environments.
 */
function bashTestProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

describe('INST-01 (Install Script)', function () {

    it('install.sh exists at project root', function () {
        expect(file_exists(bashTestProjectRoot().'/install.sh'))->toBeTrue();
    });

    it('install.sh has correct shebang', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('#!/usr/bin/env bash');
    });

    it('install.sh uses strict error handling', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('set -euo pipefail');
    });

    it('install.sh checks for php', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('command -v php');
    });

    it('install.sh checks for composer', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('command -v composer');
    });

    it('install.sh checks for node', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('command -v node');
    });

    it('install.sh checks for npm', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('command -v npm');
    });

    it('install.sh enforces PHP 8.2 minimum', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('8.2');
    });

    it('install.sh delegates to composer run setup', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('composer run setup');
    });

    it('install.sh supports apt package manager', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('apt');
    });

    it('install.sh supports dnf package manager', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('dnf');
    });

    it('install.sh supports pacman package manager', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('pacman');
    });

    it('install.sh offers to start dev server', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('composer run dev');
    });

    it('install.sh is executable', function () {
        expect(is_executable(bashTestProjectRoot().'/install.sh'))->toBeTrue();
    });

});

describe('INST-03 (Homebrew)', function () {

    it('install.sh checks for brew', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('command -v brew');
    });

    it('install.sh checks Apple Silicon Homebrew path', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('/opt/homebrew/bin/brew');
    });

    it('install.sh contains Homebrew install URL', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh');
    });

    it('install.sh explains why Homebrew is needed', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('package manager');
    });

});

describe('HLTH-01 (Health Check Hint)', function () {

    it('install.sh mentions /healthz endpoint', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('/healthz');
    });

    it('install.sh includes curl command for health check', function () {
        $content = file_get_contents(bashTestProjectRoot().'/install.sh');

        expect($content)->toContain('curl http://localhost:8000/healthz');
    });

});

describe('INST-04 (Line Endings)', function () {

    it('.gitattributes enforces LF on shell scripts', function () {
        $content = file_get_contents(bashTestProjectRoot().'/.gitattributes');

        expect($content)->toContain('*.sh text eol=lf');
    });

});
