ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev libxml2-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd zip dom mbstring \
    && if ! php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'; then docker-php-ext-install pdo_sqlite; fi \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /work
COPY . /work
CMD ["python3", "tests/run.py"]
