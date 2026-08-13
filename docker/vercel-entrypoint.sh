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
php artisan migrate --force --no-interaction

# Vercel injects PORT. Listen on every interface so its router can reach Laravel.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-80}"
