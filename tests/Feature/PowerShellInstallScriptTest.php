<?php

/**
 * Resolve the project root directory for file-based assertions.
 * Uses __DIR__ relative navigation to avoid relying on base_path(),
 * which can resolve to the wrong directory in git worktree environments.
 */
function psTestProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

describe('INST-02 (PowerShell Install Script)', function () {

    it('install.ps1 exists at project root', function () {
        expect(file_exists(psTestProjectRoot().'/install.ps1'))->toBeTrue();
    });

    it('install.ps1 uses strict error handling', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('$ErrorActionPreference = "Stop"');
    });

    it('install.ps1 uses strict mode', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('Set-StrictMode -Version 2');
    });

    it('install.ps1 checks for php', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('Get-Command');
        expect($content)->toContain('php');
    });

    it('install.ps1 checks for composer', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('composer');
    });

    it('install.ps1 checks for node', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('node');
    });

    it('install.ps1 checks for npm', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('npm');
    });

    it('install.ps1 enforces PHP 8.2 minimum', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('8.2');
    });

    it('install.ps1 uses Chocolatey for installs', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('choco install');
    });

    it('install.ps1 delegates to composer run setup', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('composer run setup');
    });

    it('install.ps1 probes Herd PHP paths', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('.config\herd\bin');
    });

    it('install.ps1 offers to start dev server', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('composer run dev');
    });

    it('install.ps1 uses Write-Host for colored output', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('Write-Host');
    });

});

describe('HLTH-01 (Health Check Hint)', function () {

    it('install.ps1 mentions /healthz endpoint', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('/healthz');
    });

    it('install.ps1 includes curl command for health check', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.ps1');

        expect($content)->toContain('curl http://localhost:8000/healthz');
    });

});

describe('INST-02 (install.bat wrapper)', function () {

    it('install.bat exists at project root', function () {
        expect(file_exists(psTestProjectRoot().'/install.bat'))->toBeTrue();
    });

    it('install.bat invokes ps1 with ExecutionPolicy Bypass', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.bat');

        expect($content)->toContain('-ExecutionPolicy Bypass');
    });

    it('install.bat checks for admin privileges', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.bat');

        expect($content)->toContain('net session');
    });

    it('install.bat elevates via UAC', function () {
        $content = file_get_contents(psTestProjectRoot().'/install.bat');

        expect($content)->toContain('-Verb RunAs');
    });

});

describe('INST-02 (Line Endings)', function () {

    it('.gitattributes enforces CRLF on PowerShell scripts', function () {
        $content = file_get_contents(psTestProjectRoot().'/.gitattributes');

        expect($content)->toContain('*.ps1 text eol=crlf');
    });

    it('.gitattributes enforces CRLF on batch files', function () {
        $content = file_get_contents(psTestProjectRoot().'/.gitattributes');

        expect($content)->toContain('*.bat text eol=crlf');
    });

});
