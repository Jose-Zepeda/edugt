#!/usr/bin/env bash
set -e

echo "=========================================="
echo "  EduGT — Despliegue en Producción (VPS)  "
echo "=========================================="

# 0. Crear memoria Swap de 2 GB si el VPS tiene poca RAM (ej. VPS de $4 USD o AWS Free Tier de 1 GB)
if [ "$(id -u)" -eq 0 ] && [ "$(free -m | awk '/^Swap:/ {print $2}')" -eq 0 ]; then
  echo "-> Configurando 2 GB de memoria Swap para estabilidad de MySQL 8..."
  fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# 1. Preparar archivo .env de Laravel si no existe
if [ ! -f backend/.env ]; then
  echo "-> Creando backend/.env desde .env.example..."
  cp backend/.env.example backend/.env
  sed -i 's/APP_ENV=local/APP_ENV=production/' backend/.env
  sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' backend/.env
  sed -i 's/DB_HOST=127.0.0.1/DB_HOST=mysql/' backend/.env
  sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=edugt_prod_secret_2026/' backend/.env
fi

# 2. Construir y levantar contenedores unificados
echo "-> Levantando contenedores Docker en segundo plano..."
docker compose -f docker-compose.prod.yml up -d --build

# 3. Instalar dependencias PHP de producción y permisos
echo "-> Instalando paquetes de Composer y ajustando permisos..."
docker exec edugt-app composer install --no-dev --optimize-autoloader
docker exec edugt-app chown -R www-data:www-data storage bootstrap/cache

# 4. Generar APP_KEY si falta
if ! grep -q "^APP_KEY=base64:" backend/.env; then
  echo "-> Generando clave criptográfica APP_KEY..."
  docker exec edugt-app php artisan key:generate --force
fi

# 5. Ejecutar migraciones y poblar el currículo (Módulos 1, 2, 3, Insignias y Pre/Post-test)
echo "-> Ejecutando migraciones y sembrado de contenidos curriculares..."
docker exec edugt-app php artisan migrate --force
docker exec edugt-app php artisan db:seed --class=Database\\Seeders\\ContentSeeder --force

# 6. Cachear configuración, rutas y vistas para máximo rendimiento
echo "-> Optimizando caché de Laravel..."
docker exec edugt-app php artisan config:cache
docker exec edugt-app php artisan route:cache
docker exec edugt-app php artisan view:cache

echo "=========================================="
echo "  ¡EduGT desplegado exitosamente en :80!  "
echo "=========================================="
