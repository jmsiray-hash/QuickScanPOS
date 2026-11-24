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
        php artisan migrate --force
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
