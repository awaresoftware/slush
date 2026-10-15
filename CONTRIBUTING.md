# Contributing to Foonk

Thanks for considering contributing to Foonk! This document covers development setup, coding conventions, and pull request guidelines.

## Development Setup

### Prerequisites

- PHP 8.4+
- Composer
- Node.js 18+
- npm

### Getting Started

1. Fork and clone the repository
2. Run the setup command:
   ```bash
   composer run setup
   ```
   This installs PHP and Node dependencies, creates the SQLite database, generates an app key, runs migrations, builds frontend assets, and links storage.
3. Start the development server:
   ```bash
   composer run dev
   ```
   This starts the PHP built-in server, Vite dev server, and queue worker concurrently. It auto-detects an available port if 8000 is in use.

## Tech Stack

- **Backend:** Laravel 13, PHP 8.4
- **Frontend:** Livewire 4, Alpine.js, Tailwind CSS 4
- **UI Components:** Mary UI (daisyUI)
- **Testing:** Pest 4
- **Database:** SQLite (native), MySQL 8.4 (Docker)

## Running Tests

```bash
# Run all tests
php artisan test --compact

# Run a specific test file
php artisan test --compact tests/Feature/YourTest.php

# Run tests matching a filter
php artisan test --compact --filter=testName
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting.

Before submitting a PR, run:

```bash
vendor/bin/pint --dirty
```

This formats only files that have been modified (git dirty files).

## Pull Request Guidelines

1. Create a feature branch from `main`
2. Write or update tests for your changes
3. Ensure all tests pass: `php artisan test --compact`
4. Run code formatting: `vendor/bin/pint --dirty`
5. Write a clear commit message describing the change
6. Open a pull request against `main`

## Project Conventions

- Use descriptive method and variable names (e.g., `isRegisteredForDiscounts`, not `discount`)
- Always use explicit return type declarations
- Use PHP 8 constructor property promotion
- Prefer Eloquent relationships over raw queries
- Use Form Request classes for validation (not inline)
- Check sibling files for naming and structure conventions before creating new files
