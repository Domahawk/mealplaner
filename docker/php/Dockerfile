FROM php:8.5-fpm-bookworm

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

# The base image includes mbstring, DOM, SQLite PDO, and built-in PHP 8.5 OPcache.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip gosu libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath pcntl \
    && groupadd --gid "$LOCAL_GID" app \
    && useradd --uid "$LOCAL_UID" --gid app --create-home app \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/starter-entrypoint
COPY docker/php/fpm-health.php /usr/local/bin/fpm-health.php
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
RUN chmod +x /usr/local/bin/starter-entrypoint \
    && sed -i 's/user = www-data/user = app/; s/group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && printf '\nping.path = /fpm-ping\n' >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
ENTRYPOINT ["starter-entrypoint"]
CMD ["php-fpm"]
