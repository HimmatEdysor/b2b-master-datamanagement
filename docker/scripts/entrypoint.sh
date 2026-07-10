#!/bin/bash
set -e

cd /var/www/html

set_env_key() {
  local key="$1"
  local value="$2"
  local allow_empty="${3:-0}"
  if [ -z "$value" ] && [ "$allow_empty" != "1" ]; then
    return 0
  fi
  if [ ! -f .env ]; then
    echo "${key}=${value}" >> .env
    return 0
  fi
  if grep -qE "^${key}=" .env; then
    sed -i.bak "s|^${key}=.*|${key}=${value}|" .env && rm -f .env.bak
  else
    echo "${key}=${value}" >> .env
  fi
}

mysql_ping_host() {
  local host="$1"
  local ping=(mysqladmin ping -h"$host" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}")
  if [ -n "${DB_PASSWORD:-}" ]; then
    ping+=(-p"${DB_PASSWORD}")
  fi
  "${ping[@]}" --silent 2>/dev/null
}

is_local_mysql_target() {
  case "${1:-}" in
    ""|127.0.0.1|localhost|::1) return 0 ;;
    *) return 1 ;;
  esac
}

resolve_local_mysql_host() {
  local gateway host seen=""
  gateway=$(ip route show default 2>/dev/null | awk 'NR==1 {print $3}')
  [ -z "$gateway" ] && gateway="172.17.0.1"

  for host in "$gateway" 127.0.0.1 host.docker.internal 172.17.0.1; do
    [ -z "$host" ] && continue
    case " $seen " in
      *" $host "*) continue ;;
    esac
    seen="$seen $host"
    if mysql_ping_host "$host"; then
      echo "$host"
      return 0
    fi
  done

  # Prefer docker bridge gateway over 127.0.0.1 (loopback inside container is not host MySQL).
  echo "$gateway"
}

if [ ! -f .env ]; then
  if [ -f .env.docker ]; then
    echo "==> Creating .env from .env.docker"
    cp .env.docker .env
  fi
fi

# Inside Docker, 127.0.0.1 in .env is the container — not Ubuntu host MySQL.
IN_DOCKER=0
[ -f /.dockerenv ] && IN_DOCKER=1

if [ "$IN_DOCKER" = "1" ] && { [ -z "${DB_MODE:-}" ] || [ "${DB_MODE:-local}" = "local" ]; }; then
  if [ -z "${DB_MODE:-}" ]; then
    export DB_MODE=local
  fi
  if [ -z "${DB_HOST:-}" ] || [ "${DB_HOST:-}" = "127.0.0.1" ]; then
    RESOLVED=$(resolve_local_mysql_host)
    export DB_HOST="$RESOLVED"
    export TENANT_DB_HOST="$RESOLVED"
    echo "==> Docker local mode — using DB_HOST=${RESOLVED} for host MySQL"
  fi
fi

if [ -n "${DB_MODE:-}" ] || [ -n "${DB_HOST:-}" ]; then
  echo "==> Syncing Docker DB/Redis endpoints into .env"
  if [ "${DB_MODE:-local}" = "container" ]; then
    DEFAULT_DB_HOST="mysql"
  else
    DEFAULT_DB_HOST=$(ip route show default 2>/dev/null | awk 'NR==1 {print $3}')
    [ -z "$DEFAULT_DB_HOST" ] && DEFAULT_DB_HOST="172.17.0.1"
  fi

  set_env_key DB_CONNECTION "${DB_CONNECTION:-mysql}"
  set_env_key DB_PORT "${DB_PORT:-3306}"
  set_env_key DB_DATABASE "${DB_DATABASE:-b2b_master}"
  set_env_key DB_USERNAME "${DB_USERNAME:-root}" 1
  set_env_key DB_PASSWORD "${DB_PASSWORD:-}" 1
  set_env_key DB_SOCKET ""

  if [ "${DB_MODE:-local}" = "local" ] && is_local_mysql_target "${DB_HOST:-127.0.0.1}"; then
    RESOLVED_DB_HOST=$(resolve_local_mysql_host)
    if [ "$RESOLVED_DB_HOST" != "${DB_HOST:-127.0.0.1}" ]; then
      echo "==> Host MySQL reachable at ${RESOLVED_DB_HOST} (not ${DB_HOST:-127.0.0.1})"
      export DB_HOST="$RESOLVED_DB_HOST"
      export TENANT_DB_HOST="$RESOLVED_DB_HOST"
    fi
    set_env_key DB_HOST "$RESOLVED_DB_HOST" 1
    set_env_key TENANT_DB_HOST "$RESOLVED_DB_HOST" 1
  elif [ "${DB_MODE:-local}" = "local" ]; then
    echo "==> DB_MODE=local with remote DB_HOST=${DB_HOST} — keeping host (use DB_MODE=rds on EC2)"
    set_env_key DB_HOST "${DB_HOST}" 1
    set_env_key TENANT_DB_HOST "${TENANT_DB_HOST:-${DB_HOST}}" 1
  else
    set_env_key DB_HOST "${DB_HOST:-$DEFAULT_DB_HOST}" 1
    set_env_key TENANT_DB_HOST "${TENANT_DB_HOST:-${DB_HOST:-$DEFAULT_DB_HOST}}" 1
  fi

  set_env_key TENANT_DB_PORT "${TENANT_DB_PORT:-${DB_PORT:-3306}}"
  set_env_key REDIS_HOST "${REDIS_HOST:-redis}"
  set_env_key REDIS_PORT "${REDIS_PORT:-6379}"
  set_env_key CACHE_STORE "${CACHE_STORE:-redis}"
  set_env_key SESSION_DRIVER "${SESSION_DRIVER:-redis}"
  set_env_key QUEUE_CONNECTION "${QUEUE_CONNECTION:-redis}"
  set_env_key APP_URL "${APP_URL:-http://localhost:8001}"
  set_env_key MASTER_APP_URL "${MASTER_APP_URL:-${APP_URL:-http://localhost:8001}}"
  set_env_key MASTER_DOMAIN "${MASTER_DOMAIN:-localhost:8001}"
  set_env_key TENANT_CRM_PATH "${TENANT_CRM_PATH:-/var/www/tenant-crm}"
  set_env_key TENANT_CRM_PORT "${TENANT_CRM_PORT:-8080}"
  set_env_key TENANT_BASE_DOMAIN "${TENANT_BASE_DOMAIN:-localhost}"
  set_env_key TENANT_URL_SCHEME "${TENANT_URL_SCHEME:-http}"
fi

echo "==> Waiting for MySQL at ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306} (mode: ${DB_MODE:-local})..."
if [ "${SKIP_DB_WAIT:-0}" != "1" ]; then
  ATTEMPTS=0
  until mysql_ping_host "${DB_HOST:-127.0.0.1}"; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
      echo "WARNING: MySQL not reachable at ${DB_HOST:-127.0.0.1}:${DB_PORT:-3306}"
      GATEWAY=$(ip route show default 2>/dev/null | awk 'NR==1 {print $3}')
      echo "  Try in .env.compose: HOST_DB_HOST=${GATEWAY:-172.17.0.1}"
      echo "  And on Ubuntu: bind-address=0.0.0.0 in /etc/mysql/mysql.conf.d/mysqld.cnf"
      break
    fi
    sleep 1
  done
  if mysql_ping_host "${DB_HOST:-127.0.0.1}"; then
    echo "==> MySQL OK at ${DB_HOST:-127.0.0.1}"
  fi
else
  echo "==> SKIP_DB_WAIT=1 — not waiting for MySQL"
fi

prepare_storage() {
  mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
  local today_file="laravel-$(date +%Y-%m-%d).log"
  touch "storage/logs/${today_file}"
  ln -sfn "${today_file}" storage/logs/laravel.log
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
}

vendor_is_ok() {
  [ -f vendor/autoload.php ] || return 1
  php -r "require 'vendor/autoload.php';" 2>/dev/null
}

ensure_app_key() {
  if [ ! -f artisan ] || [ ! -f .env ]; then
    return 0
  fi
  if grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
    return 0
  fi
  if ! vendor_is_ok; then
    echo "==> Cannot generate APP_KEY — vendor/ is incomplete"
    return 1
  fi
  echo "==> Generating APP_KEY..."
  php artisan key:generate --force
}

if [ -n "${CRM_MASTER_API_TOKEN:-}" ]; then
  set_env_key CRM_MASTER_API_TOKEN "${CRM_MASTER_API_TOKEN}" 1
fi

rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php 2>/dev/null || true

if [ "${FAST_START:-0}" = "1" ] && vendor_is_ok; then
  echo "==> FAST_START — skipping composer/npm/migrate (update mode)"
  prepare_storage
  ensure_app_key || exit 1
  if [ -f artisan ]; then
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
    rm -f bootstrap/cache/config.php 2>/dev/null || true
  fi
  echo "==> Starting: $*"
  exec "$@"
fi

NEED_COMPOSER=0
if [ "${FORCE_COMPOSER:-0}" = "1" ] || ! vendor_is_ok; then
  NEED_COMPOSER=1
elif [ "${APP_ENV:-local}" = "production" ] && [ -d vendor/nunomaduro/collision ]; then
  NEED_COMPOSER=1
fi

if [ "$NEED_COMPOSER" = "1" ] && [ -f composer.json ]; then
  if [ -d vendor ] && ! vendor_is_ok; then
    echo "==> Removing incomplete vendor/ (stale volume or interrupted install)"
    rm -rf vendor
  fi
  echo "==> Installing PHP dependencies (composer)..."
  if [ "${APP_ENV:-local}" = "production" ]; then
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  else
    composer install --prefer-dist --no-interaction
  fi
  if ! vendor_is_ok; then
    echo "FATAL: composer install finished but vendor/ is still broken"
    exit 1
  fi
else
  echo "==> PHP vendor/ present — skipping composer"
fi

NEED_NPM=0
if [ "${FORCE_NPM:-0}" = "1" ] || [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
  NEED_NPM=1
fi

if [ "$NEED_NPM" = "1" ] && [ -f package.json ] && [ "${SKIP_NPM:-0}" != "1" ]; then
  echo "==> Installing Node dependencies..."
  npm ci --no-audit --no-fund 2>/dev/null || npm install --no-audit --no-fund
fi

if [ "${SKIP_VITE_BUILD:-0}" != "1" ] && [ -f package.json ] && [ ! -d public/build ]; then
  echo "==> Building frontend assets (Vite)..."
  npm run build || echo "Vite build skipped/failed"
elif [ "${SKIP_VITE_BUILD:-0}" != "1" ] && [ -f public/build/manifest.json ]; then
  echo "==> public/build present — skipping Vite build"
fi

echo "==> Preparing Laravel storage..."
prepare_storage

if [ -f artisan ]; then
  ensure_app_key || exit 1

  if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    echo "==> Running migrations..."
    timeout 120 php artisan migrate --force || echo "Migrations failed or DB not ready yet"
  fi

  php artisan storage:link 2>/dev/null || true
  php artisan config:clear 2>/dev/null || true
  php artisan route:clear 2>/dev/null || true
  php artisan view:clear 2>/dev/null || true
  rm -f bootstrap/cache/config.php 2>/dev/null || true
fi

echo "==> Starting: $*"
exec "$@"
