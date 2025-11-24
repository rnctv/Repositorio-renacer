#!/usr/bin/env bash
# v2 - funciona con o sin sudo (si eres root no usa sudo)
set -euo pipefail

APP_DIR="/var/www/laravel"
STORAGE="$APP_DIR/storage"
CACHE="$APP_DIR/bootstrap/cache"

# Descubrir usuario del webserver / php-fpm
detect_web_user() {
  if [ -n "${WWW_USER:-}" ]; then
    echo "$WWW_USER"; return
  fi
  for u in www-data apache nginx; do
    if id "$u" >/dev/null 2>&1; then echo "$u"; return; fi
  done
  if pgrep -a php-fpm >/dev/null 2>&1; then
    ps -o user= -p "$(pgrep -n php-fpm)" | awk '{print $1}'; return
  fi
  echo "www-data"
}

WEBUSER="$(detect_web_user)"

# Si eres root, no uses sudo. Si no, úsalo si existe.
if [ "$(id -u)" -eq 0 ]; then
  SUDO=""
else
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Necesitas ejecutar como root o instalar sudo." >&2
    exit 1
  fi
fi

echo ">> Usando usuario web: $WEBUSER"
cd "$APP_DIR"

$SUDO chown -R "$WEBUSER:$WEBUSER" "$STORAGE" "$CACHE"

$SUDO find "$STORAGE" -type d -exec chmod 775 {} \;
$SUDO find "$STORAGE" -type f -exec chmod 664 {} \;
$SUDO find "$CACHE"   -type d -exec chmod 775 {} \;
$SUDO find "$CACHE"   -type f -exec chmod 664 {} \;

# setgid para que hereden grupo
$SUDO chmod g+s "$STORAGE" "$CACHE" || true

# limpiar views dañados
$SUDO rm -f "$STORAGE/framework/views/"*.php || true

# ACL opcional
if command -v setfacl >/dev/null 2>&1; then
  ME="$(id -un)"
  $SUDO setfacl -R -m u:"$WEBUSER":rwx -m u:"$ME":rwx "$STORAGE" "$CACHE" || true
  $SUDO setfacl -dR -m u:"$WEBUSER":rwx -m u:"$ME":rwx "$STORAGE" "$CACHE" || true
fi

# SELinux opcional
if command -v getenforce >/dev/null 2>&1 && [ "$(getenforce)" != "Disabled" ]; then
  $SUDO chcon -R -t httpd_sys_rw_content_t "$STORAGE" "$CACHE" || true
fi

php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan optimize

echo ">> Listo. Abre /agenda y prueba."

