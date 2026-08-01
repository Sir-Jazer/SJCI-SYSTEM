#!/usr/bin/env sh
# Startup for the container: prepare the database, then serve.
set -e

# Store the SQLite DB under /data. Mount a Railway Volume at /data to make the
# demo data survive restarts; without a volume it still works (just resets on
# each fresh container, which also re-seeds the demo data below).
export DB_CONNECTION=sqlite
export DB_DATABASE=/data/database.sqlite
mkdir -p /data

FRESH=0
if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    FRESH=1
fi

# Finalise the framework now that the environment exists.
php artisan package:discover --ansi || true
php artisan storage:link || true

# Apply schema.
php artisan migrate --force

# Seed demo data on a brand-new database so the panel isn't empty for the demo.
# Set SEED_DEMO=false in Railway variables to skip (e.g. once it holds real data).
if [ "$FRESH" = "1" ] && [ "${SEED_DEMO:-true}" = "true" ]; then
    php artisan db:seed --class=DemoSeeder --force || true
fi

# Serve on the port Railway assigns.
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8080}"