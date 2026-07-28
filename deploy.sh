#!/usr/bin/env bash
#
# Deploys the API in place. Run on the server, from the project root.
#
# Safe to re-run: it resets to origin/main, so a half-finished manual edit on
# the server is discarded rather than merged.

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

# Composer refuses to run as root without this, and the server has no other user.
export COMPOSER_ALLOW_SUPERUSER=1

echo "→ code"
git fetch origin main
git reset --hard origin/main

echo "→ dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ database"
# The schema belongs to this project, so migrating on deploy is correct here.
php artisan migrate --force

echo "→ storage link"
# public/storage → storage/app/public, so uploaded covers/logos are servable.
# Idempotent; already-linked is fine, so don't let it trip `set -e`.
php artisan storage:link || true

echo "→ admin panel assets"
# Filament serves compiled CSS/JS from public/. Republish on every deploy so
# the panel doesn't 404 its own assets after a Filament upgrade.
php artisan filament:assets

echo "→ caches"
php artisan config:cache
php artisan route:cache
# The API ships no Blade of its own, but Filament does — filament:optimize
# caches its components + Blade icons (the panel's equivalent of view:cache).
php artisan filament:optimize

echo "→ permissions"
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 775 storage bootstrap/cache
chmod 640 .env

echo "→ opcache"
# Without this the old bytecode keeps serving after the files change.
systemctl reload php8.3-fpm

echo "✓ deployed"
