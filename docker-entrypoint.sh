#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "Invalid PORT: expected a numeric port, received '$PORT'." >&2
        exit 1
        ;;
esac

sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html
php artisan config:clear
php artisan route:clear

DB_CONNECTION="${DB_CONNECTION:-mysql}"
case "$DB_CONNECTION" in
    mysql)
        if [ -n "${DB_URL:-}" ]; then
            DATABASE_SOURCE="DB_URL"
        elif [ -n "${DATABASE_URL:-}" ]; then
            export DB_URL="$DATABASE_URL"
            DATABASE_SOURCE="DATABASE_URL"
        else
            DATABASE_SOURCE="DB_HOST/DB_DATABASE"
        fi

        MISSING_DATABASE_VALUES=""
        if [ -z "${DB_URL:-}" ]; then
            for REQUIRED_VALUE in DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
                eval "REQUIRED_VALUE_SET=\${$REQUIRED_VALUE:-}"
                if [ -z "$REQUIRED_VALUE_SET" ]; then
                    MISSING_DATABASE_VALUES="${MISSING_DATABASE_VALUES} ${REQUIRED_VALUE}"
                fi
            done

            if [ -n "$MISSING_DATABASE_VALUES" ]; then
                echo "Missing database environment variables:${MISSING_DATABASE_VALUES}" >&2
                exit 1
            fi
        fi

        if ! MIGRATION_SCHEMA_STATUS="$(
            php -r '
                require "vendor/autoload.php";
                $app = require "bootstrap/app.php";
                $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
                $hasLegacyMigrationsTable =
                    Illuminate\Support\Facades\Schema::hasTable("migrations")
                    && ! Illuminate\Support\Facades\Schema::hasColumn("migrations", "migration");
                echo $hasLegacyMigrationsTable ? "legacy" : "current";
            '
        )"; then
            echo "Could not inspect the database migrations table. Verify the database connection and TLS CA settings." >&2
            exit 1
        fi

        case "$MIGRATION_SCHEMA_STATUS" in
            legacy)
                echo "Incompatible legacy migrations table detected. Running migrate:fresh; this will drop all tables and data in the configured database."
                if ! php artisan migrate:fresh --force; then
                    echo "Fresh database migration failed. Verify the Render database host, port, database name, username, password, and TLS CA settings." >&2
                    exit 1
                fi
                ;;
            current)
                echo "Running migrations using ${DATABASE_SOURCE}."
                if ! php artisan migrate --force; then
                    echo "Database migration failed. Verify the Render database host, port, database name, username, password, and TLS CA settings." >&2
                    exit 1
                fi
                ;;
            *)
                echo "Unexpected migrations table check result: ${MIGRATION_SCHEMA_STATUS}" >&2
                exit 1
                ;;
        esac
        ;;
    sqlite)
        SQLITE_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
        case "$SQLITE_DATABASE" in
            /*) ;;
            *) SQLITE_DATABASE="/var/www/html/${SQLITE_DATABASE#./}" ;;
        esac

        SQLITE_DIRECTORY="$(dirname "$SQLITE_DATABASE")"
        mkdir -p "$SQLITE_DIRECTORY"
        touch "$SQLITE_DATABASE"
        chown www-data:www-data "$SQLITE_DIRECTORY" "$SQLITE_DATABASE"
        chmod 775 "$SQLITE_DIRECTORY"
        chmod 664 "$SQLITE_DATABASE"
        php artisan migrate --force
        ;;
    *)
        echo "Unsupported DB_CONNECTION '$DB_CONNECTION'; startup migrations support mysql and sqlite." >&2
        exit 1
        ;;
esac

exec "$@"
