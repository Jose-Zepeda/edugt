FROM php:8.2-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev zip oniguruma-dev mysql-client $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl zip bcmath opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist || true

COPY . .
RUN composer dump-autoload -o

EXPOSE 9000
CMD ["php-fpm"]
