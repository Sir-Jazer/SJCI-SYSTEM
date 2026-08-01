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

# On a brand-new database, either load rich demo data OR just create the main
# church + a Head Pastor login (a clean, empty start you can enter real data into).
#   SEED_DEMO=true  (default) -> demo data
#   SEED_DEMO=false           -> empty, with only the bootstrap admin account
if [ "$FRESH" = "1" ]; then
    if [ "${SEED_DEMO:-true}" = "true" ]; then
        php artisan db:seed --class=DemoSeeder --force || true
    else
        php artisan sjci:bootstrap-admin || true
    fi
fi

# Serve on the port Railway assigns.
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8080}"