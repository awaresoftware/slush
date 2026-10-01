---
phase: 22
slug: readme-rewrite
status: draft
nyquist_compliant: false
wave_0_complete: true
created: 2026-03-27
---

# Phase 22 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | N/A — documentation-only phase |
| **Config file** | none |
| **Quick run command** | `grep -c '## macOS\|## Linux\|## Windows\|## Docker' README.md` |
| **Full suite command** | Manual checklist review |
| **Estimated runtime** | ~5 seconds (grep checks) |

---

## Sampling Rate

- **After every task commit:** Run grep checks for required headings and admonition
- **After every plan wave:** Full manual checklist below
- **Before `/gsd:verify-work`:** Full checklist must be green
- **Max feedback latency:** 5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 22-01-01 | 01 | 1 | DOCS-01 | manual | `grep -c '## macOS' README.md` | N/A | ⬜ pending |
| 22-01-02 | 01 | 1 | DOCS-01 | manual | `grep -c '## Linux' README.md` | N/A | ⬜ pending |
| 22-01-03 | 01 | 1 | DOCS-01 | manual | `grep -c '## Windows' README.md` | N/A | ⬜ pending |
| 22-01-04 | 01 | 1 | DOCS-01 | manual | `grep -c '## Docker' README.md` | N/A | ⬜ pending |
| 22-01-05 | 01 | 1 | DOCS-02 | manual | `grep -c 'IMPORTANT' README.md` | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test framework needed for documentation phase.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| README has macOS/Linux/Windows/Docker sections with step-by-step instructions | DOCS-01 | Documentation content review | Verify each `## {OS}` section has numbered installation steps with correct commands |
| Docker section has container path admonition | DOCS-02 | Visual rendering check | Verify `> [!IMPORTANT]` block shows host-path vs container-path example |
| compose.music.yaml 3-step process documented | DOCS-02 | Content completeness | Verify copy, set MUSIC_LIBRARY_PATH, restart steps are present |
| Health check URLs correct per environment | DOCS-01 | Cross-reference accuracy | Native: `localhost:8000/healthz`, Docker: `localhost/healthz` |
| LICENSE file exists with MIT text | DOCS-01 | File existence | `test -f LICENSE` |
| CONTRIBUTING.md has setup instructions | DOCS-01 | Content completeness | Verify `composer run setup` and `composer run dev` are documented |
| No unverified commands in README | DOCS-01, DOCS-02 | Cross-reference accuracy | All commands match Verified Command Inventory in RESEARCH.md |

---

## Validation Sign-Off

- [x] All tasks have manual verify or Wave 0 dependencies
- [x] Sampling continuity: grep checks after each commit
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
