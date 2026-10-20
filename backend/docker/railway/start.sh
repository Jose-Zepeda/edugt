#!/bin/sh
set -e

echo "=========================================="
echo "  EduGT — Iniciando contenedor Railway    "
echo "=========================================="

# 1. Configurar puerto dinámico inyectado por Railway ($PORT)
LISTEN_PORT="${PORT:-8080}"
echo "-> Configurando Nginx en el puerto ${LISTEN_PORT}..."
sed "s/__PORT__/${LISTEN_PORT}/g" /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# 2. Asegurar directorios y permisos de Laravel
cd /var/www/html
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 3. Limpiar cachés previas y optimizar configuración en tiempo de ejecución
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 4. Ejecutar migraciones y seeders curriculares de forma idempotente
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "-> Ejecutando migraciones de base de datos..."
    php artisan migrate --force
    echo "-> Sembrando catálogo curricular, insignias y diagnósticos (idempotente)..."
    php artisan db:seed --force
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 5. Iniciar PHP-FPM en segundo plano y Nginx en primer plano
echo "-> Levantando PHP-FPM y Nginx..."
php-fpm -D
exec nginx -g "daemon off;"
