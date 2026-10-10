# ---------------------------------------------------------------------------
# SLUSH Windows Installer
# Detects and installs PHP 8.2+, Composer, Node.js, and npm via Chocolatey,
# then runs the app setup and optionally starts the dev server.
#
# Targets PowerShell 5.1+ (Windows built-in)
# ---------------------------------------------------------------------------

$ErrorActionPreference = "Stop"
Set-StrictMode -Version 2

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

function Write-Ok {
    param([string]$Msg)
    Write-Host "[OK]   $Msg" -ForegroundColor Green
}

function Write-Fail {
    param([string]$Msg)
    Write-Host "[FAIL] $Msg" -ForegroundColor Red
}

function Write-Warn {
    param([string]$Msg)
    Write-Host "[WARN] $Msg" -ForegroundColor Yellow
}

function Write-Header {
    param([string]$Title)
    Write-Host ""
    Write-Host $Title -ForegroundColor White
    Write-Host ("-" * $Title.Length) -ForegroundColor White
}

# ---------------------------------------------------------------------------
# Utility functions
# ---------------------------------------------------------------------------

function Test-CommandExists {
    param([string]$Name)
    return $null -ne (Get-Command -Name $Name -ErrorAction SilentlyContinue)
}

function Find-HerdPhp {
    <#
    .SYNOPSIS
    Probe Laravel Herd's PHP installations at $env:USERPROFILE\.config\herd\bin\phpXX\
    Returns path to php.exe if found, otherwise $null.
    #>
    $herdBinBase = Join-Path $env:USERPROFILE ".config\herd\bin"
    foreach ($phpDir in @('php84', 'php83', 'php82')) {
        $phpExe = Join-Path $herdBinBase "$phpDir\php.exe"
        if (Test-Path $phpExe) {
            return $phpExe
        }
    }
    return $null
}

function Add-ToUserPath {
    param([string]$Directory)
    $currentPath = [Environment]::GetEnvironmentVariable('PATH', 'User')
    if ($currentPath -notlike "*$Directory*") {
        $newPath = "$currentPath;$Directory"
        [Environment]::SetEnvironmentVariable('PATH', $newPath, 'User')
        $env:PATH = "$env:PATH;$Directory"
        Write-Warn "Added $Directory to your user PATH. You may need to restart your terminal for it to take effect in all sessions."
    }
}

function Refresh-EnvPath {
    <#
    .SYNOPSIS
    Refresh the current session PATH after Chocolatey installs.
    Prefers Chocolatey's own Update-SessionEnvironment if available.
    #>
    $chocoProfile = "$env:ChocolateyInstall\helpers\chocolateyProfile.psm1"
    if (Test-Path $chocoProfile) {
        Import-Module $chocoProfile -ErrorAction SilentlyContinue
        if (Get-Command -Name Update-SessionEnvironment -ErrorAction SilentlyContinue) {
            Update-SessionEnvironment
            return
        }
    }

    # Fallback: manually merge Machine + User PATH
    $machinePath = (Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager\Environment' -Name 'Path' -ErrorAction SilentlyContinue).Path
    $userPath = (Get-ItemProperty -Path 'HKCU:\Environment' -Name 'Path' -ErrorAction SilentlyContinue).Path
    $combined = ($machinePath, $userPath | Where-Object { $_ }) -join ';'
    if ($combined) {
        $env:PATH = $combined
    }
}

function Install-Chocolatey {
    Write-Warn "Chocolatey not found — installing Chocolatey package manager..."
    Set-ExecutionPolicy Bypass -Scope Process -Force
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
    Invoke-Expression ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
    Refresh-EnvPath
}

function Ensure-Chocolatey {
    if (-not (Test-CommandExists 'choco')) {
        Install-Chocolatey
    }
}

# ---------------------------------------------------------------------------
# Dependency check functions
# ---------------------------------------------------------------------------

function Check-Php {
    # Probe Herd PHP first (D-02)
    $herdPhpExe = Find-HerdPhp
    if ($null -ne $herdPhpExe) {
        $herdBinDir = Split-Path $herdPhpExe -Parent
        Add-ToUserPath $herdBinDir
        Refresh-EnvPath
    }

    # Fall back to choco install if still not on PATH
    if (-not (Test-CommandExists 'php')) {
        Write-Warn "PHP not found — installing via Chocolatey..."
        Ensure-Chocolatey
        choco install php -y --no-progress
        Refresh-EnvPath
    }

    # PHP version gate — minimum 8.2
    $version = & php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;"
    $parts = $version -split '\.'
    $major = [int]$parts[0]
    $minor = [int]$parts[1]

    if ($major -lt 8 -or ($major -eq 8 -and $minor -lt 2)) {
        Write-Fail "PHP $version is below the minimum required version."
        Write-Host "  Minimum required: PHP 8.2" -ForegroundColor Red
        Write-Host "  Please upgrade PHP and re-run: install.bat" -ForegroundColor Red
        exit 1
    }

    Write-Ok "PHP $version"
}

function Check-Composer {
    if (-not (Test-CommandExists 'composer')) {
        Write-Warn "Composer not found — installing via Chocolatey..."
        Ensure-Chocolatey
        choco install composer -y --no-progress
        Refresh-EnvPath
    }
    Write-Ok "Composer"
}

function Check-Node {
    if (-not (Test-CommandExists 'node')) {
        Write-Warn "Node.js not found — installing via Chocolatey..."
        Ensure-Chocolatey
        choco install nodejs -y --no-progress
        Refresh-EnvPath
    }
    Write-Ok "Node.js $(& node --version)"
}

function Check-Npm {
    if (-not (Test-CommandExists 'npm')) {
        Write-Fail "npm not found."
        Write-Host "  npm should be installed alongside Node.js." -ForegroundColor Red
        Write-Host "  Try reinstalling Node.js and re-run: install.bat" -ForegroundColor Red
        exit 1
    }
    Write-Ok "npm $(& npm --version)"
}

# ---------------------------------------------------------------------------
# Main flow
# ---------------------------------------------------------------------------

Write-Header "SLUSH Installer"
Write-Header "Checking dependencies..."

Check-Php
Check-Composer
Check-Node
Check-Npm

Write-Header "Running app setup..."

& composer run setup

Write-Host ""
Write-Header "Health check"
Write-Host "  Once the dev server is running, verify your installation:"
Write-Host "    curl http://localhost:8000/healthz" -ForegroundColor Cyan
Write-Host ""

Write-Header "Setup complete!"

$answer = Read-Host "Start dev server? [Y/n]"
if ([string]::IsNullOrWhiteSpace($answer) -or $answer -match '^[Yy]$') {
    & composer run dev
}
