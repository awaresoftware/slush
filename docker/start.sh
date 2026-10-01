#!/usr/bin/env bash
set -e

cd /var/www/html

# Generate APP_KEY if missing (same guard as composer run setup)
php -r "\$e=file_exists('.env')?file_get_contents('.env'):'';preg_match('/^APP_KEY=base64:/m',\$e)||passthru('php artisan key:generate --force --no-interaction');"

# Run migrations on every boot — idempotent, picks up new migrations after git pull (D-02)
php artisan migrate --force

# Build frontend assets only when manifest is missing — skip on subsequent restarts (D-03)
if [ ! -f /var/www/html/public/build/manifest.json ]; then
    echo "Building frontend assets (first run)..."
    npm install
    npm run build
fi

# Create storage symlink — idempotent with --force
php artisan storage:link --force

# Hand off to the PHP web server directly. Sail's supervisord path needs root
# (its config and stdout fds are root-owned) but this script runs as sail, so
# exec the server command supervisord would have run.
exec ${SUPERVISOR_PHP_COMMAND:-/usr/bin/php -d variables_order=EGPCS artisan serve --host=0.0.0.0 --port=80}
