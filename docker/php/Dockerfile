FROM php:8.1-fpm-alpine

RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["sh", "docker/php/entrypoint.sh"]
