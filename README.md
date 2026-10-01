# SLUSH

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?style=flat&logo=livewire&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat&logo=php&logoColor=white) ![License](https://img.shields.io/badge/License-MIT-blue?style=flat)

A self-hosted music streaming server for your personal collection. Upload tracks, link local folders, and stream from any browser.

- Music upload with automatic ID3 metadata extraction (title, artist, album, cover art)
- Local folder linking with recursive scanning of existing music libraries
- Album and artist organization built from metadata
- Playlist creation and management
- Per-user audio streaming with queue management
- Background processing via queued jobs — uploads never block the UI
- Mobile-friendly responsive interface

## Installation

Choose your platform below. Each guide takes you from a fresh clone to a running app.

[macOS](#macos) | [Linux](#linux) | [Windows](#windows) | [Docker](#docker)

## macOS

```bash
git clone https://github.com/awaresoftware/slush.git
cd slush
./install.sh
```

The install script checks for Homebrew (offers to install it if missing), then ensures PHP 8.2+, Composer, and Node.js are installed. It runs `composer run setup` to complete the installation.

> You may be prompted for your password (sudo) during package installation.

After install, start the development server:

```bash
composer run dev
```

Verify your installation:

```bash
curl http://localhost:8000/healthz
```

<details>
<summary>Manual installation</summary>

**Prerequisites:** PHP 8.2+, Composer, Node.js 18+

```bash
git clone https://github.com/awaresoftware/slush.git
cd slush
composer run setup
composer run dev
```

</details>

## Linux

```bash
git clone https://github.com/awaresoftware/slush.git
cd slush
./install.sh
```

The install script detects your package manager (apt, dnf, or pacman) and installs PHP 8.2+, Composer, and Node.js if missing. It runs `composer run setup` to complete the installation.

Supported distributions: Debian/Ubuntu, Fedora/RHEL, Arch Linux.

> You may be prompted for your password (sudo) during package installation.

After install, start the development server:

```bash
composer run dev
```

Verify your installation:

```bash
curl http://localhost:8000/healthz
```

<details>
<summary>Manual installation</summary>

**Prerequisites:** PHP 8.2+, Composer, Node.js 18+

```bash
git clone https://github.com/awaresoftware/slush.git
cd slush
composer run setup
composer run dev
```

</details>

## Windows

```
git clone https://github.com/awaresoftware/slush.git
cd slush
install.bat
```

Double-click `install.bat` or run it from Command Prompt. It will request administrator access (UAC prompt) — this is expected and required. The script detects Laravel Herd's PHP or installs PHP, Composer, and Node.js via Chocolatey. It runs `composer run setup` to complete the installation.

> **A Windows security prompt will appear asking for administrator permissions. Click Yes to continue.**

After install, start the development server:

```bash
composer run dev
```

Verify your installation:

```bash
curl http://localhost:8000/healthz
```

<details>
<summary>Manual installation</summary>

**Prerequisites:** PHP 8.2+, Composer, Node.js 18+ (install via Chocolatey, Scoop, or Laravel Herd)

```
git https://github.com/awaresoftware/slush.git
cd slush
composer run setup
composer run dev
```

</details>

## Docker

**Prerequisites:** Docker Desktop (or Docker Engine + Compose on Linux).

```bash
git clone https://github.com/awaresoftware/slush.git
cd slush
cp .env.docker.example .env
./vendor/bin/sail up -d
```

On first run, the container generates an app key, runs migrations, installs npm packages, and builds frontend assets. This may take a minute.

Verify your installation:

```bash
curl http://localhost/healthz
```

### Linking a Local Music Library

> [!IMPORTANT]
> When linking local music folders in Docker, you must use **container paths**, not host paths.
>
> 1. Set `MUSIC_LIBRARY_PATH` in your `.env` to your host folder (e.g., `/Users/you/Music`)
> 2. Copy the override file: `cp compose.music.yaml compose.override.yaml`
> 3. Restart: `./vendor/bin/sail up -d`
> 4. In the app, link folders using the container path `/music`, not your host path
>
> | Path type | Example | Works? |
> |-----------|---------|--------|
> | Container path | `/music/Albums` | Yes |
> | Host path | `/Users/you/Music/Albums` | No |

**Changing the web port:** Set `APP_PORT` in your `.env` to use a port other than the default 80.

**Linux permissions:** Set `WWWUSER` and `WWWGROUP` in `.env` to match your host user (default: 1000).

## Getting Started

Once the app is running:

1. **Register the first account** — the first user automatically becomes the admin
2. **Upload music** via the web interface — drag and drop files or use the file picker; metadata is extracted automatically
3. **Link a local folder** from Settings to scan an existing music library on disk
4. **Create playlists** and browse your library organized by album and artist

## Configuration

Notable `.env` variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | Base URL for the app | `http://localhost:8000` (native), `http://localhost` (Docker) |
| `APP_PORT` | Web server port (Docker only) | `80` |
| `MUSIC_LIBRARY_PATH` | Host path to mount as `/music` in Docker | — |
| `QUEUE_CONNECTION` | Queue driver | `database` (native), `redis` (Docker) |

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup and guidelines.

## License

This project is open-sourced under the [MIT License](LICENSE).
