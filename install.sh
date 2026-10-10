#!/usr/bin/env bash
set -euo pipefail

# ---------------------------------------------------------------------------
# SLUSH Installer
# Detects and installs PHP 8.2+, Composer, Node.js, and npm, then runs the
# app setup and optionally starts the dev server.
#
# Supported platforms:
#   macOS  — via Homebrew
#   Linux  — via apt (Debian/Ubuntu), dnf (Fedora/RHEL), or pacman (Arch)
# ---------------------------------------------------------------------------

# TTY / Color detection (D-09, D-10)
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[0;33m'
    BOLD='\033[1m'
    RESET='\033[0m'
else
    RED='' GREEN='' YELLOW='' BOLD='' RESET=''
fi

# Output helpers
print_ok()     { printf "${GREEN}[OK]${RESET}   %s\n" "$1"; }
print_fail()   { printf "${RED}[FAIL]${RESET} %s\n" "$1"; }
print_warn()   { printf "${YELLOW}[WARN]${RESET} %s\n" "$1"; }
print_header() {
    printf "\n${BOLD}%s${RESET}\n" "$1"
    printf '%*s\n' "${#1}" '' | tr ' ' '-'
}

# Sudo helper — runs commands directly when already root (D-06 pitfall 6)
maybe_sudo() {
    if [ "$(id -u)" = "0" ]; then
        "$@"
    else
        sudo "$@"
    fi
}

# ---------------------------------------------------------------------------
# OS detection
# ---------------------------------------------------------------------------

OS=""
PKG_MGR=""

detect_os() {
    case "$(uname -s)" in
        Darwin) OS="macos" ;;
        Linux)  OS="linux" ;;
        *)
            print_fail "Unsupported operating system: $(uname -s)"
            exit 1
            ;;
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

# ---------------------------------------------------------------------------
# Homebrew check (macOS only — D-02)
# ---------------------------------------------------------------------------

check_homebrew() {
    # Check PATH first
    if command -v brew >/dev/null 2>&1; then
        return 0
    fi

    # Apple Silicon default path — not on PATH until shellenv is evaluated
    if [ -x /opt/homebrew/bin/brew ]; then
        eval "$(/opt/homebrew/bin/brew shellenv)"
        return 0
    fi

    # Intel Homebrew path
    if [ -x /usr/local/bin/brew ]; then
        eval "$(/usr/local/bin/brew shellenv)"
        return 0
    fi

    # Homebrew is missing — prompt user to install it (do not auto-install)
    print_warn "Homebrew is not installed."
    printf "\n"
    printf "  Homebrew is the package manager this script uses to install\n"
    printf "  PHP, Composer, and Node.js on macOS. Without it, dependencies\n"
    printf "  cannot be installed automatically.\n"
    printf "\n"
    printf "  Install Homebrew by running:\n"
    printf "    /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\"\n"
    printf "\n"
    printf "  Then re-run: ./install.sh\n\n"
    exit 1
}

# ---------------------------------------------------------------------------
# macOS install helpers
# ---------------------------------------------------------------------------

install_php_macos()      { brew install php; }
install_composer_macos() { brew install composer; }
install_node_macos()     { brew install node; }

# ---------------------------------------------------------------------------
# Linux install helpers
# ---------------------------------------------------------------------------

install_php_linux() {
    case "$PKG_MGR" in
        apt)
            maybe_sudo apt-get update -q
            maybe_sudo apt-get install -y php-cli php-mbstring php-xml php-curl php-zip unzip
            ;;
        dnf)
            maybe_sudo dnf install -y php-cli php-mbstring php-xml php-curl php-zip unzip
            ;;
        pacman)
            maybe_sudo pacman -S --noconfirm php
            ;;
    esac
}

install_composer_linux() {
    case "$PKG_MGR" in
        apt)    maybe_sudo apt-get install -y composer ;;
        dnf)    maybe_sudo dnf install -y composer ;;
        pacman) maybe_sudo pacman -S --noconfirm composer ;;
    esac
}

install_node_linux() {
    case "$PKG_MGR" in
        apt)
            # NodeSource LTS provides Node 22 LTS (distro packages may ship older versions)
            curl -fsSL https://deb.nodesource.com/setup_lts.x | maybe_sudo bash -
            maybe_sudo apt-get install -y nodejs
            ;;
        dnf)    maybe_sudo dnf install -y nodejs npm ;;
        pacman) maybe_sudo pacman -S --noconfirm nodejs npm ;;
    esac
}

# ---------------------------------------------------------------------------
# Dependency checks (D-01, D-04, D-05)
# ---------------------------------------------------------------------------

check_php() {
    if ! command -v php >/dev/null 2>&1; then
        print_warn "PHP not found — installing..."
        if [ "$OS" = "macos" ]; then
            install_php_macos
        else
            install_php_linux
        fi
        hash -r
    fi

    # PHP version gate — minimum 8.2 (D-05)
    # Uses PHP constants to avoid sort -V which is unavailable on macOS BSD sort
    if ! php -r 'exit(PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION >= 2) ? 0 : 1);'; then
        local installed
        installed=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        print_fail "PHP $installed is below the minimum required version."
        printf "  Minimum required: PHP 8.2\n"
        printf "  Please upgrade PHP and re-run: ./install.sh\n\n"
        exit 1
    fi

    print_ok "PHP $(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
}

check_composer() {
    if ! command -v composer >/dev/null 2>&1; then
        print_warn "Composer not found — installing..."
        if [ "$OS" = "macos" ]; then
            install_composer_macos
        else
            install_composer_linux
        fi
        hash -r
    fi

    print_ok "Composer"
}

check_node() {
    if ! command -v node >/dev/null 2>&1; then
        print_warn "Node.js not found — installing..."
        if [ "$OS" = "macos" ]; then
            install_node_macos
        else
            install_node_linux
        fi
        hash -r
    fi

    print_ok "Node.js $(node --version)"
}

check_npm() {
    if ! command -v npm >/dev/null 2>&1; then
        print_fail "npm not found."
        printf "  npm should be installed alongside Node.js.\n"
        printf "  Try reinstalling Node.js and re-run: ./install.sh\n\n"
        exit 1
    fi

    print_ok "npm $(npm --version)"
}

# ---------------------------------------------------------------------------
# Main flow
# ---------------------------------------------------------------------------

print_header "SLUSH Installer"

detect_os

if [ "$OS" = "macos" ]; then
    check_homebrew
else
    detect_linux_pkg_manager
fi

print_header "Checking dependencies..."

check_php
check_composer
check_node
check_npm

print_header "Running app setup..."

composer run setup

printf "\n"
print_header "Health check"
printf "  Once the dev server is running, verify your installation:\n"
printf "    curl http://localhost:8000/healthz\n"
printf "\n"

print_header "Setup complete!"

read -rp "Start dev server? [Y/n] " answer
answer="${answer:-Y}"

if [[ "$answer" =~ ^[Yy]$ ]]; then
    exec composer run dev
fi
