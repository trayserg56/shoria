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
RUN sed -i 's/^pm.max_children = .*/pm.max_children = 12/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/^pm.start_servers = .*/pm.start_servers = 3/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 2/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 5/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/^;pm.max_requests = .*/pm.max_requests = 500/' /usr/local/etc/php-fpm.d/www.conf \
  && sed -i 's/^;catch_workers_output = .*/catch_workers_output = yes/' /usr/local/etc/php-fpm.d/www.conf
