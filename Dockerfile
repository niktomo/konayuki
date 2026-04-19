ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# Build deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev autoconf pkg-config build-essential \
    && rm -rf /var/lib/apt/lists/*

# Required extensions: apcu (counter), pcntl (fork in collision-stress), redis (RedisAtomicCounter)
RUN pecl install apcu redis \
    && docker-php-ext-enable apcu redis \
    && docker-php-ext-install pcntl

# Enable APCu in CLI (required for benchmarks and konayuki-doctor)
RUN { \
        echo 'apc.enabled=1'; \
        echo 'apc.enable_cli=1'; \
        echo 'apc.shm_size=256M'; \
    } > /usr/local/etc/php/conf.d/apcu.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
