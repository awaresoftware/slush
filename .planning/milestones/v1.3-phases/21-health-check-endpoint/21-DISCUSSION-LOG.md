# Phase 21: Health Check Endpoint - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-27
**Phase:** 21-health-check-endpoint
**Areas discussed:** Response shape, Failure behavior, Script integration

---

## Response Shape

| Option | Description | Selected |
|--------|-------------|----------|
| Minimal | Just `{ "status": "ok", "database": "ok" }` — enough for scripts to verify | |
| Detailed | Include app version, uptime, database latency, and PHP version alongside status | ✓ |
| Tiered | Minimal by default, `?verbose=1` query param adds extra detail | |

**User's choice:** Detailed
**Notes:** None

### Follow-up: Version Source

| Option | Description | Selected |
|--------|-------------|----------|
| config('app.version') | Add a version key to config/app.php — single source of truth | ✓ |
| composer.json version field | Read from composer.json at runtime — slightly unconventional | |
| You decide | Claude picks the best approach | |

**User's choice:** config('app.version')
**Notes:** None

---

## Failure Behavior

### HTTP Status Code

| Option | Description | Selected |
|--------|-------------|----------|
| 503 Service Unavailable | Standard for health checks — signals app is up but dependency is down | ✓ |
| 500 Internal Server Error | Simpler — any non-200 means unhealthy | |

**User's choice:** 503 Service Unavailable
**Notes:** None

### Database Timeout

| Option | Description | Selected |
|--------|-------------|----------|
| 3-second timeout | If DB doesn't respond within 3s, return 503 | ✓ |
| No timeout | Let PHP's default timeout handle it | |
| You decide | Claude picks a reasonable timeout value | |

**User's choice:** 3-second timeout
**Notes:** None

---

## Script Integration

### Install Script Verification

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, add verification | After composer run setup, scripts curl /healthz to confirm app + DB working | ✓ |
| No, keep scripts as-is | Phase 21 only creates the endpoint, no script changes | |
| Endpoint only, document for manual use | Create endpoint but just document how to manually curl it | |

**User's choice:** Yes, add verification
**Notes:** None

### Docker Compose Integration

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, use /healthz in compose | Add healthcheck to web container that curls /healthz | ✓ |
| No, leave Docker as-is | Keep /healthz for install scripts only | |
| You decide | Claude determines if worth adding to Docker | |

**User's choice:** Yes, use /healthz in compose
**Notes:** None

---

## Claude's Discretion

- Database connectivity method (SELECT 1 vs connection ping)
- Exact placement of health verification in install scripts
- Docker compose healthcheck timing parameters

## Deferred Ideas

None — discussion stayed within phase scope
