#!/usr/bin/env bash
set -euo pipefail

# =========================
# CONFIG DEL USUARIO
# =========================
# Puedes pasar APP_DIR y OUTPUT_DIR como argumentos:
#   ./collect_laravel_bundle.sh /var/www/laravel /tmp
APP_DIR="${1:-/var/www/laravel}"
OUTPUT_DIR="${2:-/tmp}"

# Control por variables de entorno (opcionales)
# Si no defines DB_NAME/USER/PASS, se leerán del .env
DB_DUMP="${DB_DUMP:-true}"                 # true|false
INCLUDE_SERVER_CONF="${INCLUDE_SERVER_CONF:-true}"  # true|false

# =========================
# VALIDACIONES INICIALES
# =========================
if [ ! -d "$APP_DIR" ]; then
  echo "ERROR: No existe APP_DIR=$APP_DIR" >&2
  exit 1
fi

cd "$APP_DIR"

if [ ! -f ".env" ]; then
  echo "ADVERTENCIA: No se encontró .env en $APP_DIR/.env" >&2
fi

# =========================
# UTIL: Leer clave de .env
# =========================
read_env_var () {
  # Uso: read_env_var NOMBRE_VAR
  # Soporta valores con o sin comillas en .env, ignora líneas comentadas.
  local key="$1"
  local val
  val="$(grep -E "^[[:space:]]*${key}=" .env 2>/dev/null | grep -v '^[[:space:]]*#' | tail -n1 | cut -d'=' -f2- | sed -E 's/^[[:space:]]*//; s/[[:space:]]*$//' || true)"
  # Quitar comillas si existen al inicio/fin
  if [[ "$val" =~ ^\".*\"$ ]]; then
    val="${val:1:${#val}-2}"
  elif [[ "$val" =~ ^\'.*\'$ ]]; then
    val="${val:1:${#val}-2}"
  fi
  printf '%s' "$val"
}

# =========================
# FECHA Y ESTRUCTURA
# =========================
DATE_STR="$(date +%F_%H%M%S)"
BUNDLE_DIR="laravel_bundle_${DATE_STR}"
BUNDLE_NAME="${BUNDLE_DIR}.tar.gz"
TMP_WORK="${OUTPUT_DIR}/${BUNDLE_DIR}"
mkdir -p "$TMP_WORK"

echo "==> Empaquetando proyecto Laravel desde: $APP_DIR"
echo "==> Área temporal: $TMP_WORK"

# =========================
# COPIA DEL PROYECTO
# =========================
echo "==> Copiando archivos del proyecto (excluyendo vendor/node_modules y cachés)…"
rsync -aH \
  --exclude ".git" \
  --exclude "vendor" \
  --exclude "node_modules" \
  --exclude "storage/framework/cache" \
  --exclude "storage/framework/sessions" \
  --exclude "storage/framework/views" \
  --exclude "storage/logs/*.gz" \
  ./ "${TMP_WORK}/project/"

# Versiones/locks útiles
for f in composer.json composer.lock package.json package-lock.json pnpm-lock.yaml yarn.lock; do
  if [ -f "$APP_DIR/$f" ]; then
    cp -f "$APP_DIR/$f" "${TMP_WORK}/project/" || true
  fi
done

# Detectar versión de Laravel
if command -v php >/dev/null 2>&1; then
  (cd "$APP_DIR" && php artisan --version) > "${TMP_WORK}/LARAVEL_VERSION.txt" 2>/dev/null || true
fi

# =========================
# DUMP DE BASE DE DATOS
# =========================
if [ "${DB_DUMP}" = "true" ]; then
  echo "==> Preparando dump de base de datos desde .env…"
  # Leer desde el .env
  DB_CONNECTION="$(read_env_var DB_CONNECTION || true)"
  DB_HOST="$(read_env_var DB_HOST || true)"
  DB_PORT="$(read_env_var DB_PORT || true)"
  DB_DATABASE="$(read_env_var DB_DATABASE || true)"
  DB_USERNAME="$(read_env_var DB_USERNAME || true)"
  DB_PASSWORD="$(read_env_var DB_PASSWORD || true)"

  # Si falta el nombre, no hacemos dump
  if [ -z "${DB_DATABASE:-}" ]; then
    echo "ADVERTENCIA: No se pudo determinar DB_DATABASE desde .env; se omite dump." >&2
  else
    DUMP_FILE="${TMP_WORK}/backup.sql"
    case "${DB_CONNECTION:-mysql}" in
      mysql|mariadb)
        if command -v mysqldump >/dev/null 2>&1; then
          echo "… realizando mysqldump de '${DB_DATABASE}'"
          # Construir flags opcionales de host/port
          MD_HOST=()
          MD_PORT=()
          if [ -n "${DB_HOST:-}" ]; then MD_HOST+=( -h "${DB_HOST}" ); fi
          if [ -n "${DB_PORT:-}" ]; then MD_PORT+=( -P "${DB_PORT}" ); fi
          if [ -n "${DB_PASSWORD:-}" ]; then
            mysqldump "${MD_HOST[@]}" "${MD_PORT[@]}" -u "${DB_USERNAME:-root}" -p"${DB_PASSWORD}" --databases "${DB_DATABASE}" > "${DUMP_FILE}"
          else
            mysqldump "${MD_HOST[@]}" "${MD_PORT[@]}" -u "${DB_USERNAME:-root}" --databases "${DB_DATABASE}" > "${DUMP_FILE}"
          fi
        else
          echo "ADVERTENCIA: mysqldump no está instalado; se omite dump." >&2
        fi
        ;;
      pgsql|postgres|postgresql)
        if command -v pg_dump >/dev/null 2>&1; then
          echo "… realizando pg_dump de '${DB_DATABASE}'"
          # Variables de entorno para pg_dump
          export PGPASSWORD="${DB_PASSWORD:-}"
          PGHOST="${DB_HOST:-localhost}"
          PGPORT="${DB_PORT:-5432}"
          PGUSER="${DB_USERNAME:-postgres}"
          pg_dump -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -F p "${DB_DATABASE}" > "${DUMP_FILE}"
          unset PGPASSWORD
        else
          echo "ADVERTENCIA: pg_dump no está instalado; se omite dump." >&2
        fi
        ;;
      *)
        echo "ADVERTENCIA: DB_CONNECTION='${DB_CONNECTION}' no soportado automáticamente; se omite dump." >&2
        ;;
    esac
  fi
fi

# =========================
# CONFIG DEL SERVIDOR (NGINX/APACHE)
# =========================
mkdir -p "${TMP_WORK}/server_conf"
if [ "${INCLUDE_SERVER_CONF}" = "true" ]; then
  # Nginx
  for p in /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/nginx/conf.d /etc/nginx/nginx.conf; do
    if [ -e "$p" ]; then
      echo "==> Adjuntando config nginx: $p"
      cp -a "$p" "${TMP_WORK}/server_conf/" 2>/dev/null || true
    fi
  done
  # Apache
  for p in /etc/apache2/sites-available /etc/apache2/sites-enabled /etc/apache2/apache2.conf; do
    if [ -e "$p" ]; then
      echo "==> Adjuntando config apache: $p"
      cp -a "$p" "${TMP_WORK}/server_conf/" 2>/dev/null || true
    fi
  done
fi

# =========================
# LOGS ÚTILES
# =========================
mkdir -p "${TMP_WORK}/logs"
if [ -d "${APP_DIR}/storage/logs" ]; then
  echo "==> Copiando logs de Laravel"
  cp -a "${APP_DIR}/storage/logs" "${TMP_WORK}/logs/laravel_logs" || true
fi
for p in /var/log/nginx /var/log/apache2; do
  if [ -d "$p" ]; then
    echo "==> Copiando logs de $(basename "$p")"
    mkdir -p "${TMP_WORK}/logs$(dirname "$p")"
    cp -a "$p" "${TMP_WORK}/logs$(dirname "$p")" || true
  fi
done

# =========================
# INFO DEL SISTEMA
# =========================
echo "==> Recolectando información del sistema"
{
  echo "==== uname -a ===="
  uname -a || true
  echo
  echo "==== /etc/os-release ===="
  cat /etc/os-release 2>/dev/null || true
  echo
  echo "==== php -v ===="
  php -v 2>/dev/null || true
  echo
  echo "==== php -m ===="
  php -m 2>/dev/null || true
  echo
  echo "==== php-fpm status (si aplica) ===="
  systemctl status php*-fpm 2>/dev/null || true
  echo
  echo "==== nginx -v / apachectl -v ===="
  nginx -v 2>&1 || true
  apachectl -v 2>/dev/null || true
} > "${TMP_WORK}/SYSTEM_INFO.txt"

# =========================
# EMPAQUETADO FINAL
# =========================
echo "==> Creando ${BUNDLE_NAME} en ${OUTPUT_DIR}"
cd "${OUTPUT_DIR}"
tar -czf "${BUNDLE_NAME}" "${BUNDLE_DIR}"

echo
echo "=========================================="
echo "Paquete generado: ${OUTPUT_DIR}/${BUNDLE_NAME}"
echo "Súbeme este archivo .tar.gz aquí en el chat."
echo "=========================================="
