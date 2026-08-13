#!/bin/sh

set -eu

database_url="${DATABASE_URL:-${DB_DATABASE_URL:-${POSTGRES_URL:-${DB_URL:-}}}}"
unpooled_database_url="${DB_DATABASE_URL_UNPOOLED:-${DB_POSTGRES_URL_NON_POOLING:-}}"

if [ -z "$database_url" ]; then
    echo "ClassCheck requires a Neon PostgreSQL DATABASE_URL in production." >&2
    echo "Install Neon from the Vercel Marketplace and redeploy the service." >&2
    exit 1
fi

export DB_CONNECTION=pgsql
export DB_URL="$database_url"
export SESSION_DRIVER=database
export CACHE_STORE=database

if [ -n "$unpooled_database_url" ]; then
    export DB_DATABASE_URL_UNPOOLED="$unpooled_database_url"
    export DB_CACHE_CONNECTION=pgsql_unpooled
    export DB_CACHE_LOCK_CONNECTION=pgsql_unpooled
fi

export QUEUE_CONNECTION=database
export SESSION_SECURE_COOKIE=true

# Listen immediately so cold containers stay available while the shared Neon
# migration lock verifies that the production schema is current.
php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-80}" &
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

wait "$server_pid"
