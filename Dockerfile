FROM php:8.2-fpm-alpine

# Instalar Nginx y extensiones PHP requeridas por Laravel 11 + Dompdf
RUN apk add --no-cache \
        nginx \
        icu-dev \
        libzip-dev \
        zip \
        oniguruma-dev \
        mysql-client \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        zip \
        bcmath \
        opcache \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 1. Instalar dependencias de Composer en capa cacheable
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# 2. Copiar código del Backend Laravel y generar autoloader optimizado
COPY backend/ /var/www/html/
RUN composer dump-autoload -o \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 3. Copiar archivos estáticos del Frontend (HTML5, CSS3, JS, PWA y Avatares)
COPY frontend/ /var/www/frontend/

# 4. Configurar plantilla de Nginx y script de arranque para Railway
RUN mkdir -p /run/nginx /etc/nginx/http.d
COPY backend/docker/railway/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY backend/docker/railway/start.sh /usr/local/bin/start-railway.sh
RUN sed -i 's/\r$//' /usr/local/bin/start-railway.sh && chmod +x /usr/local/bin/start-railway.sh

EXPOSE 8080

CMD ["/usr/local/bin/start-railway.sh"]
