ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev libxml2-dev libonig-dev libsqlite3-dev libpq-dev; \
    docker-php-ext-configure gd --with-jpeg --with-webp; \
    docker-php-ext-install -j2 gd mbstring pdo_sqlite pdo_mysql pdo_pgsql zip; \
    a2enmod rewrite headers env; \
    sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf; \
    printf '%s\n' 'upload_max_filesize=64M' 'post_max_size=70M' 'memory_limit=256M' 'display_errors=Off' 'expose_php=Off' > /usr/local/etc/php/conf.d/voilavoila.ini; \
    rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p storage && chown -R www-data:www-data content storage
CMD ["apache2-foreground"]
