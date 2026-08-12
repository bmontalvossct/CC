#!/bin/sh

set -eu

# Keep a SQLite deployment bootable until a persistent database is connected.
# The database lives in Vercel's writable temporary directory and is recreated
# when an instance is replaced, so production data must use PostgreSQL/MySQL.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    export DB_DATABASE="${DB_DATABASE:-/tmp/classcheck.sqlite}"
    export SESSION_DRIVER=cookie
    export CACHE_STORE=array
    export QUEUE_CONNECTION=sync

    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    php artisan migrate --force --no-interaction
fi

# Vercel injects PORT. Listen on every interface so its router can reach Laravel.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-80}"
