---
status: complete
phase: 21-health-check-endpoint
source: 21-01-SUMMARY.md
started: 2026-03-29T12:00:00Z
updated: 2026-03-29T12:05:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Health Check Endpoint Returns 200
expected: Visit /healthz in your browser (no login required). You should see a JSON response with HTTP 200 containing: `status` ("ok"), `version`, `php` (PHP version string), and `database` object with `status` ("ok") and `latency_ms` (number).
result: pass

### 2. Endpoint Accessible Without Authentication
expected: Visit /healthz while logged out (use incognito/private window). The endpoint should return the JSON health response directly — no redirect to login page, no 401/403 error.
result: pass

### 3. Docker Compose Healthcheck Block
expected: Open compose.yaml and look at the laravel.test service. There should be a `healthcheck` block that curls /healthz, with `start_period: 60s` configured.
result: pass

### 4. Install Script Health Check Hints
expected: Open install.sh and install.ps1. Both should contain a "Health check" section with a hint showing how to verify the app via curl to /healthz. These are informational hints (printf/Write-Host), not live curl commands.
result: pass

### 5. App Version Configuration
expected: Open config/app.php. There should be a `version` key that reads from APP_VERSION env var with a default of "1.0.0". The /healthz response's `version` field should reflect this value.
result: pass

## Summary

total: 5
passed: 5
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none yet]
