#!/bin/sh
# Start the PHP-FPM pool, then keep Apache in the foreground as the container process.
set -e
if [ "${SOMI_MIGRATE_ON_START:-0}" = "1" ]; then
    php /var/www/html/database/migrate-customer-orders.php --apply
fi
if [ "${SOMI_STORAGE_DRIVER:-local}" = "local" ] && [ -n "${SOMI_STORAGE_ROOT:-}" ]; then
    php /var/www/html/bin/storage-init.php
fi
php-fpm --test >/dev/null
if [ "${SOMI_RUN_WORKERS:-0}" = "1" ]; then
    exec /usr/bin/supervisord -n -c /etc/supervisor/somi.conf
fi
php-fpm --daemonize
exec apache2ctl -D FOREGROUND
