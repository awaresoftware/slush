# Phase 19: Bash Install Script - Research

**Researched:** 2026-03-27
**Domain:** Bash scripting — dependency detection, auto-install, OS detection, ANSI output, .gitattributes LF enforcement
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Auto-install missing dependencies silently without asking. Detect what's missing and install it immediately.
- **D-02:** Exception: Homebrew on macOS. If Homebrew itself is missing, stop and prompt the user to install it (explain why it's needed). Don't auto-install a package manager.
- **D-03:** Support three Linux package managers: apt (Ubuntu/Debian), dnf (Fedora/RHEL), pacman (Arch). Auto-detect which is available and use it.
- **D-04:** Check four core dependencies only: PHP 8.2+, Composer, Node.js, npm. No Git check (user cloned the repo), no PHP extension checks (bundled by default).
- **D-05:** Enforce minimum PHP 8.2+ version. Fail on PHP 5.x/7.x/8.0/8.1. Let Composer handle exact version compatibility beyond that.
- **D-06:** After all deps pass, automatically run `composer run setup` (no confirmation prompt).
- **D-07:** After setup completes, ask "Start dev server? [Y/n]" and run `composer run dev` if confirmed.
- **D-08:** Native install path only. No Docker detection or alternative path suggestions.
- **D-09:** Clean professional output with status icons (checkmark/cross/arrow). ANSI colors: green=pass, red=fail, yellow=warn. Section headers. No emoji.
- **D-10:** Auto-detect TTY for color support. Use colors when interactive, plain text when piped or redirected.
- **D-11:** Fail fast on critical errors. Stop immediately with a clear error message and guidance.
- **D-12:** `.gitattributes` already has `* text=auto eol=lf`. Verify if explicit `*.sh text eol=lf` line is needed for INST-04.

### Claude's Discretion

- Exact bash functions and script structure (helper functions, error traps, etc.)
- How to detect the OS (macOS vs Linux) and distro family
- Specific package names per distro (e.g., `php` vs `php8.4` vs `php-cli`)
- Node.js installation method on Linux (NodeSource, distro packages, or nvm)
- Whether to use `set -euo pipefail` or manual error checking

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INST-01 | User can run `install.sh` on macOS/Linux to detect missing deps (PHP 8.x, Composer, Node) and get guided installation prompts | Script structure, detection patterns, auto-install commands per OS/distro documented below |
| INST-03 | Install script offers to install Homebrew on macOS if missing, with explanation of why it's needed | Homebrew detection pattern, prompt pattern, and official install URL documented below |
| INST-04 | Repository includes `.gitattributes` enforcing LF line endings for shell scripts | `.gitattributes` analysis — current `* text=auto eol=lf` covers `.sh` files; explicit `*.sh text eol=lf` adds clarity and is the community best practice |
</phase_requirements>

---

## Summary

Phase 19 creates `install.sh`: a single-file bash script that checks for PHP 8.2+, Composer, Node.js, and npm; auto-installs whatever is missing via the platform's package manager (Homebrew on macOS, apt/dnf/pacman on Linux); delegates the full app setup to the existing `composer run setup` command; and optionally launches the dev server. A companion `.gitattributes` change explicitly marks `*.sh` files as `text eol=lf` for CRLF protection on Windows checkouts.

The script is pure bash — no dependencies beyond the POSIX userland that comes with macOS and any modern Linux distro. The existing `docker/start.sh` uses `#!/usr/bin/env bash` and `set -e`, which is a confirmed project convention. This phase follows the same shebang and extends the error-handling discipline to `set -euo pipefail` (appropriate for an interactive install script that must never silently proceed past a failure).

**Primary recommendation:** Use `#!/usr/bin/env bash`, `set -euo pipefail`, a `trap ... ERR EXIT` cleanup handler, `[ -t 1 ]` for TTY-aware color output, and `command -v` for all binary presence checks. Delegate all app setup logic to `composer run setup` — do not re-implement it in bash.

---

## Standard Stack

### Core

| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| bash | 3.2+ (macOS ships 3.2; Linux ≥4) | Script interpreter | Only portable shell that supports arrays and `pipefail` |
| `command -v` | POSIX | Binary detection (preferred over `which`) | POSIX-portable; no external binary; returns 0/1 |
| `sort -V` | GNU coreutils | Semantic version comparison | Handles multi-segment versions correctly without `bc` or awk |
| `uname -s` | POSIX | OS detection (Darwin vs Linux) | Standard on all POSIX systems |
| `/etc/os-release` | FHS standard | Linux distro detection | Standard across Debian, RHEL, Arch, and derivatives |
| `[ -t 1 ]` | POSIX | TTY detection for colors | Standard pattern used by GNU ls, grep, curl |

### Supporting

| Tool | Version | Purpose | When to Use |
|------|---------|---------|-------------|
| `tput colors` | terminfo | Confirm terminal color support | Use together with `[ -t 1 ]` for robust detection |
| `trap ... EXIT` | bash built-in | Cleanup on unexpected exit | Always set for install scripts that create temp files |
| `printf` | POSIX | Colored/formatted output | Prefer over `echo` for portability and escape sequences |
| Homebrew `brew` | 4.x | macOS package manager | Only supported package manager on macOS for PHP install |
| NodeSource | LTS setup script | Node.js on apt/dnf distros | Provides current LTS (v22) when distro packages are too old |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `command -v dep` | `which dep` | `which` is not POSIX and absent on some minimal Linux images; `command -v` is always available |
| `sort -V` version compare | awk-based split | `sort -V` is simpler and available on macOS (via Homebrew coreutils) and GNU; awk is available everywhere but more verbose |
| NodeSource setup script | distro `nodejs` package | Distro repos often ship Node 18 or older; NodeSource guarantees LTS (v22) |
| distro `php` package | shivammathur tap | `brew install php` gives PHP 8.5 (latest); `shivammathur/php/php@8.4` gives a specific pinned version — for a minimum-version check, `brew install php` is fine |

**Installation:**

```bash
# No npm/pip dependencies — install.sh is a standalone bash file
# Runtime dependencies are what the script installs:
#   macOS: brew install php composer node
#   apt:   sudo apt install -y php-cli composer nodejs npm
#   dnf:   sudo dnf install -y php composer nodejs npm
#   pacman: sudo pacman -S --noconfirm php composer nodejs npm
```

---

## Architecture Patterns

### Recommended Script Structure

```
install.sh
├── shebang + set -euo pipefail
├── trap cleanup EXIT
├── Color/TTY detection ([ -t 1 ])
├── Helper: print_ok / print_fail / print_warn / print_info
├── Helper: check_version_min (uses sort -V)
├── detect_os() → sets OS="macos" or OS="linux"
├── detect_linux_pkg_manager() → sets PKG_MGR="apt|dnf|pacman"
├── check_dep PHP / Composer / Node / npm
│   └── if missing: install_<dep>_<os>()
├── Separator: "Running app setup..."
├── composer run setup (unconditional)
└── Prompt: "Start dev server? [Y/n]" → composer run dev
```

### Pattern 1: Shebang and Safety Flags

**What:** Always use `#!/usr/bin/env bash` and `set -euo pipefail` as the first two lines of a bash install script.

**When to use:** Any bash script that must not silently continue past an error.

```bash
#!/usr/bin/env bash
set -euo pipefail
```

`-e` — exit immediately on error
`-u` — treat unset variables as errors
`-o pipefail` — fail the pipeline if any command in a pipe fails

Note: `set -e` alone is already used in `docker/start.sh`. Adding `-u` and `pipefail` is correct for an interactive install script where unexpected variable gaps should be fatal.

### Pattern 2: TTY Detection for Colors

**What:** Check `[ -t 1 ]` (stdout is a terminal) before emitting ANSI codes. Also respect `NO_COLOR`.

**When to use:** Every script that emits colored output (per D-10).

```bash
# Source: POSIX standard / GNU ls convention
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    BOLD='\033[1m'
    RESET='\033[0m'
else
    RED='' GREEN='' YELLOW='' BOLD='' RESET=''
fi

print_ok()   { printf "${GREEN}[OK]${RESET}  %s\n" "$1"; }
print_fail() { printf "${RED}[FAIL]${RESET} %s\n" "$1"; }
print_warn() { printf "${YELLOW}[WARN]${RESET} %s\n" "$1"; }
print_info() { printf "${BOLD}-->${RESET} %s\n" "$1"; }
```

### Pattern 3: Binary Presence Check with command -v

**What:** Use `command -v dep >/dev/null 2>&1` to test if a binary is on PATH.

**When to use:** All four dependency checks (php, composer, node, npm).

```bash
# Source: POSIX standard
if ! command -v php >/dev/null 2>&1; then
    print_fail "PHP not found"
    install_php
fi
```

Never use `which` — it is not POSIX and absent on some minimal images.

### Pattern 4: Semantic Version Comparison with sort -V

**What:** Compare PHP's installed version against minimum 8.2 using `sort -V`.

**When to use:** PHP version gate (D-05).

```bash
# Source: GNU coreutils sort -V documented behavior
check_php_version() {
    local installed
    installed=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    local minimum="8.2"
    if [ "$(printf '%s\n' "$minimum" "$installed" | sort -V | head -n1)" != "$minimum" ]; then
        print_fail "PHP $installed is below minimum 8.2"
        exit 1
    fi
}
```

Note: `php -r 'echo PHP_MAJOR_VERSION...'` is more reliable than parsing `php -v` output with grep/sed.

### Pattern 5: OS Detection

**What:** Use `uname -s` to branch macOS vs Linux; use `command -v` to detect the Linux package manager.

**When to use:** Before any install step.

```bash
detect_os() {
    case "$(uname -s)" in
        Darwin) OS="macos" ;;
        Linux)  OS="linux" ;;
        *)      print_fail "Unsupported OS: $(uname -s)"; exit 1 ;;
    esac
}

detect_linux_pkg_manager() {
    if command -v apt >/dev/null 2>&1; then
        PKG_MGR="apt"
    elif command -v dnf >/dev/null 2>&1; then
        PKG_MGR="dnf"
    elif command -v pacman >/dev/null 2>&1; then
        PKG_MGR="pacman"
    else
        print_fail "No supported package manager found (apt, dnf, pacman)"
        exit 1
    fi
}
```

Note: Do NOT check `/etc/os-release` for the distro name — check for the binary directly. A container running Ubuntu may have had `apt` replaced, and checking the binary avoids mapping distro names to commands.

### Pattern 6: Homebrew Detection and Prompt (macOS)

**What:** Check both Intel (`/usr/local/bin/brew`) and Apple Silicon (`/opt/homebrew/bin/brew`) paths. If neither exists and `command -v brew` fails, prompt the user and exit.

**When to use:** macOS path only (D-02).

```bash
# Source: Homebrew official docs — two standard install paths
check_homebrew() {
    if command -v brew >/dev/null 2>&1; then
        return 0
    fi
    # Apple Silicon path not on PATH yet?
    if [ -x /opt/homebrew/bin/brew ]; then
        eval "$(/opt/homebrew/bin/brew shellenv)"
        return 0
    fi
    # Intel path
    if [ -x /usr/local/bin/brew ]; then
        eval "$(/usr/local/bin/brew shellenv)"
        return 0
    fi

    print_warn "Homebrew is not installed."
    printf "\n  Homebrew is the package manager this script uses to install\n"
    printf "  PHP, Composer, and Node.js on macOS. Without it, dependencies\n"
    printf "  cannot be installed automatically.\n\n"
    printf "  Install Homebrew by running:\n"
    printf "    /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\"\n\n"
    printf "  Then re-run: ./install.sh\n\n"
    exit 1
}
```

### Pattern 7: Composer Installation (programmatic)

**What:** Official Composer programmatic install verifies checksum before executing.

**When to use:** When `composer` is not found on PATH (all platforms where PHP is already present).

```bash
# Source: https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md
install_composer_binary() {
    local tmp_dir
    tmp_dir=$(mktemp -d)
    trap 'rm -rf "$tmp_dir"' EXIT

    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', '$tmp_dir/composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '$tmp_dir/composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        print_fail "Composer installer checksum verification failed"
        rm -rf "$tmp_dir"
        exit 1
    fi

    php "$tmp_dir/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp_dir"
}
```

On macOS, prefer `brew install composer` if Homebrew is available — cleaner and managed.
On Linux with apt/dnf/pacman, prefer the package manager's composer package first; fall back to the programmatic method if the package is not available.

### Pattern 8: Node.js Installation on Linux

**What:** Use distro package manager for Node. For apt, use NodeSource LTS setup script to get Node 22 LTS; for dnf and pacman, the distro packages are recent enough.

**When to use:** When `node` is not found and OS is Linux (D-03).

```bash
# apt — NodeSource LTS (Node 22)
# Source: https://nodesource.com / https://deb.nodesource.com/
install_node_apt() {
    curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
    sudo apt-get install -y nodejs
}

# dnf — distro package is acceptable (Fedora ships recent Node)
install_node_dnf() {
    sudo dnf install -y nodejs npm
}

# pacman — nodejs npm together
install_node_pacman() {
    sudo pacman -S --noconfirm nodejs npm
}
```

### Pattern 9: PHP Installation per Platform

**What:** On macOS use `brew install php` (gives PHP 8.5). On Linux use the package manager's PHP CLI package.

**When to use:** When `php` is not found or version is below 8.2.

```bash
# macOS — brew install php gives 8.5 (current stable)
# Source: https://formulae.brew.sh/formula/php
install_php_macos() {
    brew install php
}

# apt — php-cli; the exact package may be php8.4 or php-cli depending on distro
install_php_apt() {
    sudo apt-get update -q
    sudo apt-get install -y php-cli php-mbstring php-xml php-curl php-zip unzip
}

# dnf
install_php_dnf() {
    sudo dnf install -y php-cli php-mbstring php-xml php-curl php-zip unzip
}

# pacman — php package includes all common extensions
install_php_pacman() {
    sudo pacman -S --noconfirm php
}
```

### Anti-Patterns to Avoid

- **Using `which` instead of `command -v`:** `which` is not POSIX and fails on some minimal environments (Alpine, Debian slim). Use `command -v` throughout.
- **Parsing `php -v` output with grep/sed to extract the version:** Fragile across locales and custom build strings. Use `php -r 'echo PHP_MAJOR_VERSION...'` instead.
- **Re-implementing the app setup sequence in bash:** `composer run setup` already contains the complete, idempotent, tested setup sequence. Do not duplicate it.
- **Using `set -e` without `-u` and `pipefail`:** Silent unset variable bugs are common in bash install scripts. Always use `set -euo pipefail`.
- **Omitting `eval "$(brew shellenv)"` after detecting Homebrew via path:** Homebrew won't be on PATH for subsequent commands in the same shell session unless shellenv is evaluated.
- **Running `brew install php` and immediately calling `php -v` without re-checking PATH:** Homebrew installs to a cellar and links; PATH is updated within the same session, but `hash -r` or a PATH refresh may be needed if the script caches command lookups.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| App setup sequence | Custom bash install sequence | `composer run setup` | Already implements idempotent, ordered setup with guards; duplicating it creates drift |
| Node.js installation on apt | Manual tarball extraction | NodeSource setup script | Handles GPG keys, repo configuration, and installs the full LTS automatically |
| Composer download + verification | Raw curl pipe | Official programmatic installer from getcomposer.org | Includes SHA384 checksum verification; avoids MITM risk |
| Version comparison arithmetic | Custom `awk` or `bc` version splitter | `sort -V` + `head -n1` | GNU sort handles multi-segment semver correctly without custom logic |
| PHP package name detection per distro | Long if/elif chain checking `/etc/os-release` NAME | `command -v apt/dnf/pacman` binary check | Binary presence is the canonical test; distro name mapping adds fragility |

**Key insight:** The entire app setup is already battle-tested in `composer run setup`. The install script's job is only to ensure the four prerequisites exist, then hand off.

---

## Common Pitfalls

### Pitfall 1: Homebrew Not on PATH After Installation (Apple Silicon)

**What goes wrong:** Script detects `brew` is absent, prints the install message and exits — but the user installs Homebrew and re-runs the script, and `command -v brew` still fails because `/opt/homebrew/bin` is not on PATH in the current shell session.

**Why it happens:** Apple Silicon installs Homebrew to `/opt/homebrew/bin` which is not in the default macOS PATH. Homebrew's post-install instructions tell the user to run `eval "$(/opt/homebrew/bin/brew shellenv)"` — but a fresh shell or a script invocation won't have done that.

**How to avoid:** In `check_homebrew`, also check `/opt/homebrew/bin/brew` and `/usr/local/bin/brew` by path before concluding Homebrew is absent. If found by path, run `eval "$(brew shellenv)"` to populate PATH for the current process.

**Warning signs:** `command -v brew` returns non-zero even though `/opt/homebrew/bin/brew` exists on disk.

### Pitfall 2: PHP Version Check Passes But Wrong PHP Is Used

**What goes wrong:** The system has PHP 7.x from a package manager but Homebrew also installed PHP 8.x. `php -v` returns the first one on PATH, which may be 7.x.

**Why it happens:** PATH precedence. `/usr/bin/php` (old system PHP) precedes `/opt/homebrew/bin/php`.

**How to avoid:** After installing PHP via Homebrew, run `brew link --force --overwrite php` or document that the user may need to restart their shell. The version check must be run after `eval "$(brew shellenv)"` has executed so Homebrew PHP precedes system PHP.

**Warning signs:** `php -v` shows an older version immediately after a successful `brew install php`.

### Pitfall 3: set -e Exits on the "dep not found" Check

**What goes wrong:** `command -v php` returns 1 when PHP is absent. With `set -e` active, the script exits immediately on that non-zero return before any install logic runs.

**Why it happens:** `set -e` exits on any non-zero command result unless the command is part of a conditional.

**How to avoid:** Always wrap the presence check in a conditional: `if ! command -v php >/dev/null 2>&1; then ...`. The `!` negation makes the compound command's exit status 0 when php is absent, which is the success case for the condition.

**Warning signs:** Script exits silently when a dep is missing instead of entering the install branch.

### Pitfall 4: CRLF on install.sh Breaks Execution on Linux

**What goes wrong:** Windows developer commits `install.sh` with CRLF line endings. When the script is checked out on Linux, bash sees `\r` at the end of each line and produces errors like `$'\r': command not found`.

**Why it happens:** Git `text=auto` normalizes line endings based on OS unless overridden by `eol=lf`.

**How to avoid:** Add `*.sh text eol=lf` to `.gitattributes`. The existing `* text=auto eol=lf` rule applies globally, but explicit per-extension rules are the community best practice for executable scripts (see INST-04). After adding the rule, existing checkouts must run `git rm --cached -r . && git reset --hard` to re-normalize.

**Warning signs:** `file install.sh` on Linux reports "CRLF line terminators" rather than "LF line terminators".

### Pitfall 5: composer run setup Called Before composer Is on PATH

**What goes wrong:** The script installs Composer but then calls `composer run setup` immediately, which fails because the shell's command hash table doesn't know about the newly installed binary.

**Why it happens:** Bash caches PATH lookups. A newly installed binary may not be visible until the hash is cleared.

**How to avoid:** After installing any binary, call `hash -r` to clear bash's command cache, or use the full path of the installed binary for the first invocation.

### Pitfall 6: apt Requires sudo But Script Is Run as Root

**What goes wrong:** On some CI or Docker environments, the user is already root. `sudo` invocations fail with "sudo: command not found" or hang waiting for a password that will never come.

**Why it happens:** Many minimal Linux images do not have `sudo` installed; root users don't need it.

**How to avoid:** Wrap `sudo` with a helper: if running as root (`[ "$(id -u)" = "0" ]`), run the command directly; otherwise prefix with `sudo`.

---

## Code Examples

Verified patterns from official sources:

### Complete Color/TTY Setup

```bash
# Source: POSIX test -t standard; NO_COLOR per https://no-color.org/
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    BOLD='\033[1m'
    RESET='\033[0m'
else
    RED='' GREEN='' YELLOW='' BOLD='' RESET=''
fi
```

### PHP Version Extraction (Reliable)

```bash
# Source: PHP CLI built-in constants — immune to locale/build variation
PHP_VERSION_INSTALLED=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
```

### Minimum Version Gate with sort -V

```bash
# Source: GNU coreutils sort(1) -V flag
version_gte() {
    # Returns 0 if $1 >= $2
    [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

if ! version_gte "$PHP_VERSION_INSTALLED" "8.2"; then
    print_fail "PHP $PHP_VERSION_INSTALLED is too old. Minimum: PHP 8.2"
    exit 1
fi
```

### Exit Trap for Temp File Cleanup

```bash
# Source: bash(1) trap pseudo-signal EXIT
TMPDIR_CREATED=""
cleanup() {
    [ -n "$TMPDIR_CREATED" ] && rm -rf "$TMPDIR_CREATED"
}
trap cleanup EXIT
```

### Sudo-Agnostic Package Install Helper

```bash
# Handle root vs non-root environments
maybe_sudo() {
    if [ "$(id -u)" = "0" ]; then
        "$@"
    else
        sudo "$@"
    fi
}
```

### .gitattributes Shell Script Rule

```
# Existing in .gitattributes:
* text=auto eol=lf

# Add explicit rule for clarity (INST-04):
*.sh text eol=lf
```

The explicit `*.sh text eol=lf` is additive and does not conflict with `* text=auto eol=lf`. More specific patterns take precedence.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-----------------|--------------|--------|
| `which dep` for binary check | `command -v dep` | POSIX 2008+ consensus | Removes dependency on external `which` binary |
| `sort --version-sort` | `sort -V` | GNU coreutils 7.0 (2009) | Short flag, widely available |
| `#!/bin/bash` shebang | `#!/usr/bin/env bash` | Convention shift ~2015 | Supports non-standard bash locations (e.g. Homebrew bash) |
| Manual trap+cleanup | `trap cleanup EXIT` (EXIT pseudo-signal) | bash 2.x | Handles SIGINT/SIGTERM/ERR uniformly |
| `echo "\033[0;32m..."` | `printf "${GREEN}...${RESET}\n"` | Long-standing | `printf` is more portable; `echo -e` behavior is shell-dependent |

**Deprecated/outdated:**

- `#!/bin/bash` as shebang: Works but `#!/usr/bin/env bash` is preferred for portability across Homebrew installs and NixOS.
- Parsing `php -v` text output: Replace with `php -r 'echo PHP_MAJOR_VERSION...'`.
- `which dep 2>/dev/null` pattern: Replace with `command -v dep >/dev/null 2>&1`.

---

## Open Questions

1. **sort -V availability on macOS without Homebrew coreutils**
   - What we know: macOS ships BSD sort, which does NOT support `-V`. GNU coreutils `sort` supports `-V` and is available after `brew install coreutils` (or via Homebrew's default `brew install gnu-sort`).
   - What's unclear: Is `sort -V` present on a clean macOS before Homebrew is installed?
   - Recommendation: On macOS, use the `version_compare` fallback using `awk` or pure-bash array splitting for the PHP version check. Alternatively, since the script already requires Homebrew before installing PHP, by the time a version check runs on macOS, coreutils may be present — but this cannot be assumed. Use the `awk`-based fallback for macOS portability, or use `php -r 'exit(PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 2 ? 0 : 1);'` instead.

2. **PHP package name on apt (Ubuntu 24.04 vs 22.04)**
   - What we know: Ubuntu 22.04 ships `php8.1` as `php-cli`. Ubuntu 24.04 ships `php8.3` as `php-cli`. The meta-package `php-cli` always installs the distro's default PHP.
   - What's unclear: If Ubuntu ships PHP 8.0 or 8.1 (Ubuntu 22.04 ships 8.1), the installed PHP may fail the 8.2 minimum check immediately after auto-install.
   - Recommendation: After `apt install php-cli`, re-run the version check. If still below 8.2, print guidance to add the `ondrej/php` PPA and exit. Do not auto-add PPAs in the install script (that is scope creep beyond D-01).

3. **Composer package availability via apt/dnf**
   - What we know: `apt install composer` and `dnf install composer` exist but may install Composer 1.x on older distros.
   - What's unclear: Current Ubuntu 24.04 and Fedora 40+ ship Composer 2.x. Older LTS (Ubuntu 22.04) ships Composer 2.2.x which is sufficient.
   - Recommendation: Use the package manager's composer package. If post-install `composer --version` returns 1.x, fall back to the official programmatic installer. This is a LOW-risk scenario for modern systems but worth a version check.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=BashInstall` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INST-01 | `install.sh` is executable and has correct shebang | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |
| INST-01 | `install.sh` contains checks for php, composer, node, npm | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |
| INST-01 | `install.sh` references `composer run setup` | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |
| INST-03 | `install.sh` checks for Homebrew on macOS | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |
| INST-03 | `install.sh` contains Homebrew install URL | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |
| INST-04 | `.gitattributes` has explicit `*.sh text eol=lf` rule | unit | `php artisan test --compact --filter=BashInstallScriptTest` | ❌ Wave 0 |

Note: These are file-content assertion tests (similar to `DockerConfigurationTest.php`). They verify the script's structure and key strings are present — the approach already proven in this codebase for infrastructure files.

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=BashInstallScriptTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/BashInstallScriptTest.php` — covers INST-01, INST-03, INST-04 (file content assertions following `DockerConfigurationTest` pattern)

*(No framework gaps — Pest 4.4 and test infrastructure already installed.)*

---

## Sources

### Primary (HIGH confidence)

- GNU bash(1) manual — `set -euo pipefail`, `trap`, `command -v`, `[ -t 1 ]`
- https://getcomposer.org/doc/faqs/how-to-install-composer-programmatically.md — official programmatic install method with SHA384 checksum verification
- https://formulae.brew.sh/formula/php — confirms `brew install php` gives PHP 8.5 (current stable)
- https://git-scm.com/docs/gitattributes — `text eol=lf` attribute semantics

### Secondary (MEDIUM confidence)

- https://deb.nodesource.com/ — NodeSource apt LTS setup command (`curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -`)
- https://nodesource.com — confirmed Node 22 is current LTS
- https://github.com/shivammathur/homebrew-php — shivammathur tap for pinned PHP versions on macOS (useful if specific version needed vs latest)
- https://docs.github.com/en/get-started/git-basics/configuring-git-to-handle-line-endings — GitHub's recommendation for `*.sh text eol=lf`

### Tertiary (LOW confidence — needs validation on live macOS)

- `sort -V` availability on stock macOS BSD sort — unverified; treat as unavailable until tested on a clean macOS system without Homebrew coreutils
- Ubuntu 22.04 apt `php-cli` package version — may ship PHP 8.1 which fails the 8.2 minimum check

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — tooling is POSIX-standard; Homebrew and NodeSource are the established install paths
- Architecture: HIGH — patterns verified against official docs (Composer installer, Homebrew paths, gitattributes)
- Pitfalls: HIGH — the `set -e` + `command -v` interaction, Homebrew PATH issue, and CRLF issue are all well-documented
- `sort -V` on macOS: LOW — requires live testing on a clean macOS without coreutils

**Research date:** 2026-03-27
**Valid until:** 2026-09-27 (stable domain; Homebrew formula and NodeSource LTS URL are stable)
