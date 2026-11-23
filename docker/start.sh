#!/bin/sh
set -e
cd /app

# Uploaded images live on a Railway volume mounted at storage/app/public;
# make sure the layout and the public/storage symlink exist on every boot.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs
php artisan storage:link --force >/dev/null 2>&1 || true

php artisan optimize

# Background workers: queued notifications and the scheduler
# (sessions:close-idle, stock:reset-daily).
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
php artisan schedule:work &

exec frankenphp run --config /etc/frankenphp/Caddyfile
