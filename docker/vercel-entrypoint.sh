#!/bin/sh

set -eu

database_url="${DATABASE_URL:-${DB_DATABASE_URL:-${POSTGRES_URL:-${DB_URL:-}}}}"

if [ -z "$database_url" ]; then
    echo "ClassCheck requires a Neon PostgreSQL DATABASE_URL in production." >&2
    echo "Install Neon from the Vercel Marketplace and redeploy the service." >&2
    exit 1
fi

export DB_CONNECTION=pgsql
export DB_URL="$database_url"
export SESSION_DRIVER=database
export CACHE_STORE=database
export QUEUE_CONNECTION=database
export SESSION_SECURE_COOKIE=true

# All Vercel instances share Neon, including users, sections, and sessions.
php artisan down --retry=5 --no-interaction || true

# Vercel injects PORT. Listen on every interface so its router can reach Laravel.
php artisan serve --host=0.0.0.0 --port="${PORT:-80}" &
server_pid=$!

stop_server() {
    kill "$server_pid" 2>/dev/null || true
}

trap stop_server INT TERM

if ! php docker/migrate-database.php; then
    echo "ClassCheck database preparation failed; keeping the application unavailable." >&2
    stop_server
    wait "$server_pid" || true
    exit 1
fi

php artisan up --no-interaction
wait "$server_pid"
