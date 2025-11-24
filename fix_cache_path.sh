#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/var/www/laravel"
WEB_USER="www-data"
WEB_GROUP="www-data"

log(){ echo "[FIX][$(date +'%Y-%m-%d %H:%M:%S')] $*"; }

if [ ! -d "$PROJECT_DIR" ]; then
  echo "ERROR: No existe $PROJECT_DIR"
  exit 1
fi

cd "$PROJECT_DIR"

log "Creando rutas requeridas por Laravel..."
# Rutas estándar que si faltan disparan 'valid cache path'
mkdir -p storage/framework/{cache,data,sessions,testing,views}
mkdir -p bootstrap/cache

log "Ajustando permisos..."
chown -R "$WEB_USER:$WEB_GROUP" storage bootstrap/cache
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
chmod -R 775 bootstrap/cache

# Asegurar que el driver 'file' apunte a una ruta válida
if [ -f .env ]; then
  # Si no hay CACHE_DRIVER, o está vacío, pon 'file'
  if ! grep -q '^CACHE_DRIVER=' .env; then
    log "Añadiendo CACHE_DRIVER=file al .env..."
    echo "CACHE_DRIVER=file" >> .env
  else
    # Normaliza a 'file' si estaba mal configurado
    sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=file/' .env
    log "CACHE_DRIVER=file garantizado."
  fi
else
  log "ADVERTENCIA: No existe .env. Laravel podría fallar por esto."
fi

# Limpiar y recalentar caches de Laravel
if [ -f artisan ]; then
  log "Limpiando y reconstruyendo caches..."
  php artisan cache:clear || true
  php artisan config:clear || true
  php artisan route:clear || true
  php artisan view:clear || true
  php artisan optimize || true
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
else
  log "No se encontró artisan; ¿es un proyecto Laravel válido?"
fi

# Descubrir paquetes y autoload por si acaso
if command -v composer >/dev/null 2>&1 && [ -f composer.json ]; then
  log "composer dump-autoload y package:discover..."
  composer dump-autoload -q || true
  php artisan package:discover --ansi || true
fi

# Reiniciar PHP-FPM si existe (en CT de Proxmox suele existir)
restart_if_exists(){
  local svc="$1"
  if systemctl list-unit-files | grep -q "^${svc}\.service"; then
    log "Reiniciando $svc..."
    systemctl restart "$svc" || true
  fi
}
# Detecta nombre de php-fpm (php8.2-fpm, etc.)
PHPFPM="$(systemctl list-units --type=service --all | awk '{print $1}' | grep -E '^php[0-9.]+-fpm\.service$' | head -n1 || true)"
if [ -n "${PHPFPM:-}" ]; then
  restart_if_exists "${PHPFPM%.service}"
else
  restart_if_exists "php-fpm"
fi
restart_if_exists "nginx"
restart_if_exists "apache2"

log "Listo. Intenta cargar el sitio nuevamente."
