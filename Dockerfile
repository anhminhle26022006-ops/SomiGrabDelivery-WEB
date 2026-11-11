# Apache (event MPM) + PHP-FPM. Unlike mod_php/prefork, idle keep-alive
# connections do not occupy a PHP worker, so many concurrent users are served
# by a small PHP pool that also caps database connections.
FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 supervisor libpq-dev libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql curl mbstring bcmath opcache pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/www/html/index.html

# Production PHP settings (no error display, OPcache without timestamp checks).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# Embedded configuration: php-production.ini
RUN printf '%s\n' \
    '; SOMI production overrides, loaded after php.ini-production.' \
    'expose_php = Off' \
    'memory_limit = 128M' \
    'max_execution_time = 30' \
    'post_max_size = 12M' \
    'upload_max_filesize = 10M' \
    '' \
    '; Fewer filesystem stats per include.' \
    'realpath_cache_size = 4096K' \
    'realpath_cache_ttl = 600' \
    '' \
    '; Compiled scripts stay in shared memory. The image is immutable, so skip the' \
    '; per-request file timestamp checks; set PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 when' \
    '; mounting source code into the container for development.' \
    'opcache.enable = 1' \
    'opcache.memory_consumption = 128' \
    'opcache.interned_strings_buffer = 16' \
    'opcache.max_accelerated_files = 10000' \
    'opcache.validate_timestamps = ${PHP_OPCACHE_VALIDATE_TIMESTAMPS}' \
    'opcache.revalidate_freq = 0' \
    'opcache.save_comments = 1' \
    '' \
    'session.use_strict_mode = 1' \
    'session.use_only_cookies = 1' \
    > "$PHP_INI_DIR/conf.d/zz-somi.ini"
# Embedded configuration: php-fpm-pool.conf
RUN printf '%s\n' \
    '; SOMI PHP-FPM pool. Loaded after the image'"'"'s www.conf/zz-docker.conf.' \
    '[global]' \
    'error_log = /proc/self/fd/2' \
    '' \
    '[www]' \
    'listen = /run/php/php-fpm.sock' \
    'listen.owner = www-data' \
    'listen.group = www-data' \
    'listen.mode = 0660' \
    '; Requests wait here (milliseconds, not keep-alive seconds) when all workers are busy.' \
    'listen.backlog = 4096' \
    '' \
    '; Bound concurrent DB users. Idle workers exit instead of reserving session' \
    '; pooler connections. Budget all replicas and background workers together;' \
    '; RAM use is roughly 15-30 MB per worker.' \
    'pm = ondemand' \
    'pm.process_idle_timeout = 10s' \
    'pm.max_children = ${SOMI_MAX_WORKERS}' \
    'pm.max_requests = 5000' \
    '' \
    '; The app reads SOMI_* / RESEND_* from the process environment.' \
    'clear_env = no' \
    '; Apache already logs every request; skip the duplicate FPM access log write.' \
    'access.log = /dev/null' \
    'catch_workers_output = yes' \
    'decorate_workers_output = no' \
    > /usr/local/etc/php-fpm.d/zz-somi.conf

# Embedded configuration: apache-performance.conf
RUN printf '%s\n' \
    '# SOMI Apache tuning (event MPM in front of PHP-FPM).' \
    'ServerName localhost' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    '' \
    '# Event MPM parks idle keep-alive connections without tying up a thread or a' \
    '# PHP worker, so hundreds of browsers can stay connected cheaply.' \
    'KeepAlive On' \
    'MaxKeepAliveRequests 500' \
    'KeepAliveTimeout 5' \
    '' \
    '# Keep enough threads warm that a traffic spike does not force Apache to drop' \
    '# idle keep-alive connections while it forks more processes.' \
    '<IfModule mpm_event_module>' \
    '    StartServers             4' \
    '    ServerLimit              16' \
    '    ThreadsPerChild          25' \
    '    MinSpareThreads          75' \
    '    MaxSpareThreads          250' \
    '    MaxRequestWorkers        400' \
    '    AsyncRequestWorkerFactor 4' \
    '    MaxConnectionsPerChild   0' \
    '</IfModule>' \
    '' \
    '# Debian'"'"'s deflate.conf omits JSON and SVG; API responses are JSON.' \
    '<IfModule mod_deflate.c>' \
    '    AddOutputFilterByType DEFLATE application/json image/svg+xml text/javascript' \
    '</IfModule>' \
    > /etc/apache2/conf-available/somi-performance.conf
# Embedded configuration: apache-site.conf
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html' \
    '' \
    '    <Directory /var/www/html>' \
    '        Options -Indexes +FollowSymLinks' \
    '        # .htaccess holds the access rules and asset caching headers.' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '' \
    '    # PHP runs in the PHP-FPM pool configured in this Dockerfile.' \
    '    <FilesMatch "\.php$">' \
    '        SetHandler "proxy:unix:/run/php/php-fpm.sock|fcgi://localhost"' \
    '    </FilesMatch>' \
    '' \
    '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf
RUN a2dismod -f mpm_prefork >/dev/null 2>&1 || true; \
    a2enmod mpm_event proxy_fcgi setenvif rewrite headers expires deflate \
    && a2enconf somi-performance \
    && mkdir -p /run/php && chown www-data:www-data /run/php \
    && ln -sfT /dev/stdout /var/log/apache2/access.log \
    && ln -sfT /dev/stderr /var/log/apache2/error.log

# Embedded configuration: start.sh
RUN printf '%s\n' \
    '#!/bin/sh' \
    '# Start the PHP-FPM pool, then keep Apache in the foreground as the container process.' \
    'set -e' \
    'if [ "${SOMI_MIGRATE_ON_START:-0}" = "1" ]; then' \
    '    php /var/www/html/database/migrate-customer-orders.php --apply' \
    fi \
    'storage_driver="${SOMI_STORAGE_DRIVER:-${SOMI_STORAGE_PROVIDER:-local}}"' \
    'if [ "$storage_driver" != "supabase" ] && [ -n "${SOMI_STORAGE_ROOT:-}" ]; then' \
    '    php /var/www/html/bin/storage-init.php' \
    fi \
    'php-fpm --test >/dev/null' \
    'if [ "${SOMI_RUN_WORKERS:-0}" = "1" ]; then' \
    '    exec /usr/bin/supervisord -n -c /etc/supervisor/somi.conf' \
    fi \
    'php-fpm --daemonize' \
    'exec apache2ctl -D FOREGROUND' \
    > /usr/local/bin/somi-start
# Embedded configuration: supervisord.conf
RUN printf '%s\n' \
    '[supervisord]' \
    nodaemon=true \
    logfile=/dev/null \
    pidfile=/run/somi-supervisord.pid \
    '' \
    '[program:php-fpm]' \
    'command=/usr/local/sbin/php-fpm --nodaemonize' \
    priority=10 \
    autorestart=true \
    stopasgroup=true \
    killasgroup=true \
    stdout_logfile=/dev/fd/1 \
    stdout_logfile_maxbytes=0 \
    stderr_logfile=/dev/fd/2 \
    stderr_logfile_maxbytes=0 \
    '' \
    '[program:apache]' \
    'command=/usr/sbin/apache2ctl -D FOREGROUND' \
    priority=20 \
    autorestart=true \
    stopasgroup=true \
    killasgroup=true \
    stdout_logfile=/dev/fd/1 \
    stdout_logfile_maxbytes=0 \
    stderr_logfile=/dev/fd/2 \
    stderr_logfile_maxbytes=0 \
    '' \
    '[program:somi-workers]' \
    'command=/usr/local/bin/php /var/www/html/bin/person3-scheduler.php' \
    user=www-data \
    priority=30 \
    autorestart=true \
    stopasgroup=true \
    killasgroup=true \
    stopwaitsecs=35 \
    stdout_logfile=/dev/fd/1 \
    stdout_logfile_maxbytes=0 \
    stderr_logfile=/dev/fd/2 \
    stderr_logfile_maxbytes=0 \
    > /etc/supervisor/somi.conf
RUN sed -i 's/\r$//' /usr/local/bin/somi-start && chmod +x /usr/local/bin/somi-start

# Concurrent PHP worker limit per container. Budget across replicas and DB tools.
ENV SOMI_MAX_WORKERS=6 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
STOPSIGNAL SIGTERM
CMD ["somi-start"]
