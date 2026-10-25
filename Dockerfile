FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev libxml2-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd zip dom mbstring \
    && if ! php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'; then docker-php-ext-install pdo_sqlite; fi \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
COPY docs/php.ini /usr/local/etc/php/conf.d/voilavoila.ini
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/content
EXPOSE 80
