FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install -j2 gd zip dom mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
COPY docs/php.ini /usr/local/etc/php/conf.d/voilavoila.ini
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/content
EXPOSE 80
