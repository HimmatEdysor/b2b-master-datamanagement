#!/bin/bash
set -e

cd /var/www/html

echo "==> Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306} (mode: ${DB_MODE:-container})..."
if [ "${SKIP_DB_WAIT:-0}" != "1" ]; then
  ATTEMPTS=0
  MYSQL_PING=(mysqladmin ping -h"${DB_HOST:-mysql}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME:-root}")
  if [ -n "${DB_PASSWORD:-}" ]; then
    MYSQL_PING+=(-p"${DB_PASSWORD}")
  fi
  until "${MYSQL_PING[@]}" --silent 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
      echo "MySQL not ready after 30s — continuing (web will start; migrations may retry)"
      break
    fi
    sleep 1
  done
else
  echo "==> SKIP_DB_WAIT=1 — not waiting for MySQL"
fi

if [ ! -f .env ]; then
  if [ -f .env.docker ]; then
    echo "==> Creating .env from .env.docker"
    cp .env.docker .env
  fi
fi

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

prepare_storage() {
  mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
  local today_file="laravel-$(date +%Y-%m-%d).log"
  touch "storage/logs/${today_file}"
  ln -sfn "${today_file}" storage/logs/laravel.log
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
  chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
}

if [ -n "${DB_MODE:-}" ] || [ -n "${DB_HOST:-}" ]; then
  echo "==> Syncing Docker DB/Redis endpoints into .env"
  set_env_key DB_CONNECTION "${DB_CONNECTION:-mysql}"
  set_env_key DB_HOST "${DB_HOST:-mysql}" 1
  set_env_key DB_PORT "${DB_PORT:-3306}"
  set_env_key DB_DATABASE "${DB_DATABASE:-b2b_master}"
  set_env_key DB_USERNAME "${DB_USERNAME:-root}" 1
  set_env_key DB_PASSWORD "${DB_PASSWORD:-}" 1
  set_env_key DB_SOCKET ""
  set_env_key TENANT_DB_HOST "${TENANT_DB_HOST:-${DB_HOST:-mysql}}" 1
  set_env_key TENANT_DB_PORT "${TENANT_DB_PORT:-${DB_PORT:-3306}}"
  set_env_key REDIS_HOST "${REDIS_HOST:-redis}"
  set_env_key REDIS_PORT "${REDIS_PORT:-6379}"
  set_env_key APP_URL "${APP_URL:-http://localhost:8001}"
  set_env_key MASTER_APP_URL "${MASTER_APP_URL:-${APP_URL:-http://localhost:8001}}"
  set_env_key MASTER_DOMAIN "${MASTER_DOMAIN:-localhost:8001}"
  set_env_key TENANT_CRM_PATH "${TENANT_CRM_PATH:-/var/www/tenant-crm}"
  set_env_key TENANT_CRM_PORT "${TENANT_CRM_PORT:-8080}"
  set_env_key TENANT_BASE_DOMAIN "${TENANT_BASE_DOMAIN:-localhost}"
  set_env_key TENANT_URL_SCHEME "${TENANT_URL_SCHEME:-http}"
fi

if [ -n "${CRM_MASTER_API_TOKEN:-}" ]; then
  set_env_key CRM_MASTER_API_TOKEN "${CRM_MASTER_API_TOKEN}" 1
fi

rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php 2>/dev/null || true

if [ "${FAST_START:-0}" = "1" ] && [ -f vendor/autoload.php ]; then
  echo "==> FAST_START — skipping composer/npm/migrate (update mode)"
  prepare_storage
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
if [ "${FORCE_COMPOSER:-0}" = "1" ] || [ ! -f vendor/autoload.php ]; then
  NEED_COMPOSER=1
elif [ "${APP_ENV:-local}" != "production" ] && [ ! -d vendor/nunomaduro/collision ]; then
  NEED_COMPOSER=1
fi

if [ "$NEED_COMPOSER" = "1" ] && [ -f composer.json ]; then
  echo "==> Installing PHP dependencies (composer)..."
  if [ "${APP_ENV:-local}" = "production" ]; then
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader || echo "Composer install failed — continuing"
  else
    composer install --prefer-dist --no-interaction || echo "Composer install failed — continuing"
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
  if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force || true
  fi

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
