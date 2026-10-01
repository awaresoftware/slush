---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Pint never formats .blade.php — never pass blade files to it explicitly
Pint's directory scan (including CI's `composer lint` = `pint --parallel`, and the AGENTS.md-mandated `pint --dirty`) skips every `*.blade.php` file — only bare `.php` files are checked, so blade components are never lint-clean under the laravel preset. Do NOT pass a blade path explicitly (e.g. `pint path/to/⚡page.blade.php`): pint will then reformat it (braces_position, class_attributes_separation), producing churn inconsistent with sibling blade components and a diff CI will never validate. Match the surrounding file's existing style instead. Verified 2026-10-01 with pint 1.30.5 on a minimal fixture: `y.php` flagged, `x.blade.php` silently ignored.
