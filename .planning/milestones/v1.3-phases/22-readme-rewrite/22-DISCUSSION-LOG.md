# Phase 22: README Rewrite - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-27
**Phase:** 22-readme-rewrite
**Areas discussed:** Document structure, Project presentation, Install guide depth, Content beyond install

---

## Document structure

### File layout

| Option | Description | Selected |
|--------|-------------|----------|
| Single README.md (Recommended) | Everything in one file — users see it immediately on GitHub. Anchored sections for each OS. | ✓ |
| README + docs/ folder | README has overview + quick start, detailed per-OS guides live in docs/ | |
| README + wiki | README has essentials, GitHub wiki has extended guides. | |

**User's choice:** Single README.md
**Notes:** Most personal/self-hosted projects use this approach.

### Section order

| Option | Description | Selected |
|--------|-------------|----------|
| Quick start first (Recommended) | Brief project intro, then jump straight to installation. Features/details after. | ✓ |
| Full intro first | Start with project description, features, screenshots, then installation. | |
| Tabbed quick start | Project intro, then single Installation section with OS sub-sections. | |

**User's choice:** Quick start first
**Notes:** Gets users running fast.

### Install path organization

| Option | Description | Selected |
|--------|-------------|----------|
| Separate H2 sections (Recommended) | Each OS gets its own ## section with full steps. Easy to link directly. | ✓ |
| Grouped native + Docker | One section for native (with OS sub-sections), one for Docker. | |
| Decision tree | "Choose your install method" branching structure. | |

**User's choice:** Separate H2 sections
**Notes:** Clear visual separation, easy deep-linking.

---

## Project presentation

### Intro content (multi-select)

| Option | Description | Selected |
|--------|-------------|----------|
| One-liner description | Brief project summary sentence. | ✓ |
| Feature highlights | Bullet list of key features. | ✓ |
| Screenshot/demo | Include a screenshot of the app UI. | ✓ |
| Tech stack badges | Shield.io badges for Laravel, Livewire, PHP, license, etc. | ✓ |

**User's choice:** All four selected
**Notes:** Full project presentation at the top.

### Screenshot

| Option | Description | Selected |
|--------|-------------|----------|
| Placeholder for now | Commented-out section for later. | |
| Skip screenshot | No screenshot section — text-only for now. | ✓ |
| I'll provide one | User has a screenshot ready. | |

**User's choice:** Skip screenshot
**Notes:** Can be added later.

---

## Install guide depth

### macOS/Linux approach

| Option | Description | Selected |
|--------|-------------|----------|
| Script-first + manual fallback (Recommended) | Lead with install.sh, collapsed manual steps below. | ✓ |
| Script only | Just the install.sh command with brief explanation. | |
| Full manual steps | Every step explicit, script as shortcut. | |

**User's choice:** Script-first + manual fallback
**Notes:** None.

### Windows approach

| Option | Description | Selected |
|--------|-------------|----------|
| Same pattern (Recommended) | Lead with install.bat, collapsed manual steps. Consistent with macOS/Linux. | ✓ |
| Script only | Just install.bat. | |
| More hand-holding | More Windows-specific guidance. | |

**User's choice:** Same pattern
**Notes:** Consistent cross-platform documentation structure.

### Docker container path caveat

| Option | Description | Selected |
|--------|-------------|----------|
| Callout box (Recommended) | GitHub > [!IMPORTANT] admonition with host vs container path example. | ✓ |
| Inline note | Mention in text flow near MUSIC_LIBRARY_PATH step. | |
| Dedicated sub-section | Separate "Local Library in Docker" sub-section. | |

**User's choice:** Callout box
**Notes:** Hard to miss, satisfies DOCS-02 requirement.

---

## Content beyond install

### Extra sections (multi-select)

| Option | Description | Selected |
|--------|-------------|----------|
| Usage / Getting Started | First steps after install: admin account, invite users, upload music, link folders. | ✓ |
| Configuration | Notable .env variables, theme toggle, queue settings. | ✓ |
| Contributing | How to contribute: fork, branch, PR. Development setup. | ✓ |
| License | License section (MIT, etc.) | ✓ |

**User's choice:** All four selected
**Notes:** None.

### License

| Option | Description | Selected |
|--------|-------------|----------|
| MIT | Permissive, standard for Laravel projects. | ✓ |
| GPL-3.0 | Copyleft. | |
| AGPL-3.0 | Network copyleft. | |
| No license yet | Skip for now. | |

**User's choice:** MIT
**Notes:** None.

### Contributing depth

| Option | Description | Selected |
|--------|-------------|----------|
| Brief inline (Recommended) | Few lines in README, no separate file. | |
| Detailed with CONTRIBUTING.md | README links to separate CONTRIBUTING.md with coding standards, PR guidelines, dev setup. | ✓ |
| Minimal | One line: "Contributions welcome." | |

**User's choice:** Detailed with CONTRIBUTING.md
**Notes:** Full contributing guide in separate file.

---

## Claude's Discretion

- Exact wording, tone, and flow of all documentation
- Badge selection and styling
- Feature highlights ordering
- Manual steps detail within collapsed sections
- Which .env vars to highlight in Configuration section
- CONTRIBUTING.md structure and content

## Deferred Ideas

None — discussion stayed within phase scope
