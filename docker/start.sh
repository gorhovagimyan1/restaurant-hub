#!/bin/sh
set -e
cd /app

# Uploaded images live on a Railway volume mounted at storage/app/public;
# make sure the layout and the public/storage symlink exist on every boot.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs
php artisan storage:link --force >/dev/null 2>&1 || true

# MySQL may still be booting (or restarting) when this container starts.
i=0
until php artisan db:show >/dev/null 2>&1; do
  i=$((i + 1))
  [ "$i" -ge 30 ] && { echo "Database not reachable after 60s" >&2; exit 1; }
  echo "Waiting for database..."
  sleep 2
done

# Single replica, so migrating on boot is safe and doesn't depend on
# Railway's pre-deploy hook.
php artisan migrate --force

php artisan optimize

# Background workers: queued notifications and the scheduler
# (sessions:close-idle, stock:reset-daily). Restart them if they exit, e.g.
# when the database drops out for a moment.
keep_running() {
  while true; do
    "$@" || true
    echo "'$*' exited, restarting in 5s" >&2
    sleep 5
  done
}
keep_running php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
keep_running php artisan schedule:work &

exec frankenphp run --config /etc/frankenphp/Caddyfile
