# DOM is provided by the official PHP image; do not rebuild it.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends python3 libpng-dev libjpeg62-turbo-dev libwebp-dev libzip-dev libxml2-dev libonig-dev libsqlite3-dev; \
    if ! php -r 'exit(extension_loaded("gd") && function_exists("imagejpeg") && function_exists("imagewebp") ? 0 : 1);'; then \
        docker-php-ext-configure gd --with-jpeg --with-webp; \
        docker-php-ext-install -j2 gd; \
    fi; \
    for vv_extension in zip mbstring pdo_sqlite; do \
        if ! php -r 'exit(extension_loaded($argv[1]) ? 0 : 1);' "$vv_extension"; then \
            docker-php-ext-install -j2 "$vv_extension"; \
        fi; \
    done; \
    php -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "PHP 8.3+ required\n"); exit(1); } foreach (["dom", "mbstring", "fileinfo", "gd", "zip", "pdo_sqlite", "tokenizer"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "Missing PHP extension: ".$e."\n"); exit(1); } } if (!function_exists("imagejpeg") || !function_exists("imagewebp")) { fwrite(STDERR, "GD JPEG/WebP support required\n"); exit(1); }'; \
    rm -rf /var/lib/apt/lists/*
WORKDIR /work
COPY . /work
RUN if [ -f BUILD-INFO.json ]; then python3 tools/verify_release.py --directory /work; fi
CMD ["python3", "tests/run.py"]
