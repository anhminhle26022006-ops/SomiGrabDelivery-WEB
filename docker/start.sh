#!/bin/sh
# Start the PHP-FPM pool, then keep Apache in the foreground as the container process.
set -e
php-fpm --test >/dev/null
php-fpm --daemonize
exec apache2ctl -D FOREGROUND
