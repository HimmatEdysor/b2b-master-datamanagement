#!/bin/bash
set -e

assert_no_merge_conflicts() {
  local file="$1"
  [ -f "$file" ] || return 0
  if grep -qE '^<<<<<<<|^>>>>>>>' "$file"; then
    echo "FATAL: $file has unresolved git merge conflict markers (<<<<<<<)."
    echo "Fix: cd /var/www/B2B_CRM && ./docker/scripts/recover-docker-scripts.sh"
    exit 1
  fi
}

cd /var/www/html

# shellcheck source=env-file.sh
source "$(dirname "$0")/env-file.sh"
assert_no_merge_conflicts "$(dirname "$0")/env-file.sh"
if [ -f /var/www/tenant-crm/docker/scripts/prepare-permissions.sh ]; then
  assert_no_merge_conflicts /var/www/tenant-crm/docker/scripts/prepare-permissions.sh
fi
if [ -f /var/www/tenant-crm/docker/scripts/prepare-permissions.sh ]; then
  # shellcheck source=/dev/null
  source /var/www/tenant-crm/docker/scripts/prepare-permissions.sh
else
  # shellcheck source=prepare-permissions.sh
  source "$(dirname "$0")/prepare-permissions.sh" 2>/dev/null || true
fi

echo "==> Master entrypoint v2 (host.docker.internal + safe vendor clear)"

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    echo "==> Creating .env from .env.example"
    cp .env.example .env
  fi
fi

# Docker local mode: use host.docker.internal (127.0.0.1 is container loopback).
if [ -n "${DB_MODE:-}" ] || [ -n "${DB_HOST:-}" ]; then
  echo "==> Syncing Docker DB/Redis endpoints into .env"
  if [ "${DB_MODE:-local}" = "container" ]; then
    DEFAULT_DB_HOST="mysql"
  else
    DEFAULT_DB_HOST=$(docker_db_host "${DB_HOST:-127.0.0.1}")
  fi

  set_env_key DB_CONNECTION "${DB_CONNECTION:-mysql}"
  set_env_key DB_PORT "${DB_PORT:-3306}"
  set_env_key DB_DATABASE "${DB_DATABASE:-b2b_master}"
  set_env_key DB_USERNAME "${DB_USERNAME:-admin}" 1
  set_env_key DB_PASSWORD "${DB_PASSWORD:-}" 1
  set_env_key DB_SOCKET ""
  set_env_key DB_HOST "$DEFAULT_DB_HOST" 1
  set_env_key TENANT_DB_HOST "$(docker_db_host "${TENANT_DB_HOST:-${DB_HOST:-127.0.0.1}}")" 1
  set_env_key TENANT_DB_PORT "${TENANT_DB_PORT:-${DB_PORT:-3306}}"
  set_env_key REDIS_HOST "${REDIS_HOST:-redis}"
  set_env_key REDIS_PORT "${REDIS_PORT:-6379}"
  set_env_key CACHE_STORE "${CACHE_STORE:-redis}"
  set_env_key SESSION_DRIVER "${SESSION_DRIVER:-redis}"
  set_env_key QUEUE_CONNECTION "${QUEUE_CONNECTION:-redis}"
  set_env_key APP_URL "${APP_URL:-http://localhost:8001}"
  if [ -n "${APP_ENV:-}" ]; then
    set_env_key APP_ENV "${APP_ENV}"
  fi
  set_env_key APP_DEBUG "${APP_DEBUG:-false}" 1
  set_env_key MASTER_APP_URL "${MASTER_APP_URL:-${APP_URL:-http://localhost:8001}}"
  set_env_key MASTER_DOMAIN "${MASTER_DOMAIN:-localhost:8001}"
  set_env_key TENANT_CRM_PATH "${TENANT_CRM_PATH:-/var/www/tenant-crm}"
  set_env_key TENANT_CRM_PORT "${TENANT_CRM_PORT:-8080}"
  set_env_key TENANT_BASE_DOMAIN "${TENANT_BASE_DOMAIN:-localhost}"
  set_env_key TENANT_URL_SCHEME "${TENANT_URL_SCHEME:-http}"
fi

MYSQL_WAIT_HOST=$(docker_db_host "${DB_HOST:-127.0.0.1}")
echo "==> Waiting for MySQL at ${MYSQL_WAIT_HOST}:${DB_PORT:-3306} (mode: ${DB_MODE:-local})..."
if [ "${SKIP_DB_WAIT:-0}" != "1" ]; then
  ATTEMPTS=0
  until mysql_ping_silent "$MYSQL_WAIT_HOST"; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
      echo "WARNING: MySQL not reachable at ${MYSQL_WAIT_HOST}:${DB_PORT:-3306}"
      echo "  Ensure Ubuntu MySQL is running and admin@'%' is granted"
      echo "  Docker local mode uses host.docker.internal (not 127.0.0.1)"
      break
    fi
    sleep 1
  done
  if mysql_ping_silent "$MYSQL_WAIT_HOST"; then
    echo "==> MySQL OK at ${MYSQL_WAIT_HOST}"
  fi
else
  echo "==> SKIP_DB_WAIT=1 — not waiting for MySQL"
fi

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
  if ! grep -qE '^APP_KEY=' .env 2>/dev/null; then
    set_env_key APP_KEY "" 1
  fi
  echo "==> Generating APP_KEY..."
  php artisan key:generate --force || write_app_key_direct
  if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
    echo "FATAL: APP_KEY was not written to .env"
    return 1
  fi
  finalize_env_file .env
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
  finalize_env_file .env
  assert_env_readable
  assert_laravel_boot
  echo "==> Starting: $*"
  if [ "$#" -eq 0 ]; then
    set -- /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
  fi
  exec "$@"
fi

NEED_COMPOSER=0
if [ "${FORCE_COMPOSER:-0}" = "1" ] || ! vendor_is_ok; then
  NEED_COMPOSER=1
elif [ "${APP_ENV:-production}" = "production" ] && [ -d vendor/nunomaduro/collision ]; then
  NEED_COMPOSER=1
fi

if [ "$NEED_COMPOSER" = "1" ] && [ -f composer.json ]; then
  if [ -d vendor ] && ! vendor_is_ok; then
    echo "==> Clearing incomplete vendor/ volume (stale or interrupted install)"
    clear_mount_dir vendor
  fi
  echo "==> Installing PHP dependencies (composer)..."
  if [ "${APP_ENV:-production}" = "production" ]; then
    composer_install_app 1
  else
    composer_install_app 0
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

  php artisan storage:link --force 2>/dev/null || php artisan storage:link 2>/dev/null || true
  php artisan config:clear 2>/dev/null || true
  php artisan route:clear 2>/dev/null || true
  php artisan view:clear 2>/dev/null || true
  rm -f bootstrap/cache/config.php 2>/dev/null || true
fi

finalize_env_file .env
assert_env_readable
assert_laravel_boot

if [ "$#" -eq 0 ]; then
  set -- /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
echo "==> Starting: $*"
exec "$@"
