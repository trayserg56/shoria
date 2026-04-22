FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    git \
    unzip \
    linux-headers \
    $PHPIZE_DEPS \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    bcmath \
    zip \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
