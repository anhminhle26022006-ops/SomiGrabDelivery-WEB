# Apache (event MPM) + PHP-FPM. Unlike mod_php/prefork, idle keep-alive
# connections do not occupy a PHP worker, so many concurrent users are served
# by a small PHP pool that also caps database connections.
FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends apache2 libpq-dev libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql curl mbstring bcmath opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /var/www/html/index.html

# Production PHP settings (no error display, OPcache without timestamp checks).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php-production.ini $PHP_INI_DIR/conf.d/zz-somi.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-somi.conf

COPY docker/apache-performance.conf /etc/apache2/conf-available/somi-performance.conf
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
RUN a2dismod -f mpm_prefork >/dev/null 2>&1 || true; \
    a2enmod mpm_event proxy_fcgi setenvif rewrite headers expires deflate \
    && a2enconf somi-performance \
    && mkdir -p /run/php && chown www-data:www-data /run/php \
    && ln -sfT /dev/stdout /var/log/apache2/access.log \
    && ln -sfT /dev/stderr /var/log/apache2/error.log

COPY docker/start.sh /usr/local/bin/somi-start
RUN sed -i 's/\r$//' /usr/local/bin/somi-start && chmod +x /usr/local/bin/somi-start

# Concurrent PHP worker limit per container. Budget across replicas and DB tools.
ENV SOMI_MAX_WORKERS=6 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
STOPSIGNAL SIGWINCH
CMD ["somi-start"]
