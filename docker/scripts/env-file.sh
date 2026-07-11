#!/bin/bash
# Safe .env writer — passwords with | & $ % ! etc. must not go through sed unescaped.

assert_no_merge_conflicts() {
  local file="$1"
  [ -f "$file" ] || return 0
  if grep -qE '^(<<<<<<<|=======|>>>>>>>)' "$file"; then
    echo "FATAL: $file has unresolved git merge conflict markers (<<<<<<<)."
    echo "Fix on server:"
    echo "  cd /var/www/b2b-master-datamanagement && git fetch && git checkout HEAD -- docker/scripts/"
    exit 1
  fi
}

set_env_key() {
  local key="$1"
  local value="$2"
  local allow_empty="${3:-0}"
  local env_file="${4:-.env}"

  if [ -z "$value" ] && [ "$allow_empty" != "1" ]; then
    return 0
  fi

  local tmp
  tmp=$(mktemp)
  if [ -f "$env_file" ]; then
    grep -v "^${key}=" "$env_file" >"$tmp" || true
  else
    : >"$tmp"
  fi
  printf '%s=%s\n' "$key" "$value" >>"$tmp"
  mv "$tmp" "$env_file"
}

finalize_env_file() {
  local env_file="${1:-.env}"
  [ -f "$env_file" ] || return 0
  if [ "$(id -u)" = "0" ] && id www-data &>/dev/null; then
    chown www-data:www-data "$env_file" 2>/dev/null || true
  fi
  chmod 640 "$env_file" 2>/dev/null || chmod 644 "$env_file" 2>/dev/null || true
}

app_key_configured() {
  if [ -n "${APP_KEY:-}" ] && [[ "${APP_KEY}" == base64:* ]]; then
    return 0
  fi
  [ -f .env ] && grep -qE '^APP_KEY=base64:' .env 2>/dev/null
}

assert_env_readable() {
  if [ ! -f .env ]; then
    echo "FATAL: .env missing — create it: cp .env.example .env"
    exit 1
  fi
  finalize_env_file .env
  if ! id www-data &>/dev/null; then
    return 0
  fi
  if ! su -s /bin/bash www-data -c 'test -r .env' 2>/dev/null; then
    echo "FATAL: php-fpm user (www-data) cannot read .env"
    ls -la .env 2>/dev/null || true
    exit 1
  fi
}

assert_laravel_boot() {
  [ -f artisan ] || return 0
  echo "==> Verifying Laravel boots as www-data..."
  local out rc=0
  out=$(su -s /bin/bash www-data -c 'php artisan about --no-ansi 2>&1' 2>&1) || rc=$?
  if [ "$rc" != "0" ]; then
    echo "FATAL: Laravel cannot boot:"
    echo "$out"
    if [ -f storage/logs/laravel.log ]; then
      echo "--- tail storage/logs/laravel.log ---"
      tail -25 storage/logs/laravel.log 2>/dev/null || true
    fi
    if [ "${APP_DEBUG:-false}" != "true" ] && [ "${APP_DEBUG:-0}" != "1" ]; then
      echo "Tip: set APP_DEBUG=1 in B2B_CRM/.env and recreate master to see errors in the browser"
    fi
    exit 1
  fi
}

# Named volume mount points cannot be removed — only their contents.
clear_mount_dir() {
  local dir="$1"
  [ -d "$dir" ] || return 0
  find "$dir" -mindepth 1 -delete 2>/dev/null || \
    rm -rf "${dir:?}"/* "${dir:?}"/.[!.]* 2>/dev/null || true
}

# Docker: DB_HOST=127.0.0.1 reaches Ubuntu host MySQL (bind-mount DB_SOCKET in compose for local mode).
is_loopback_db_host() {
  case "$1" in
    127.0.0.1|localhost|::1) return 0 ;;
    *) return 1 ;;
  esac
}

docker_db_host() {
  local host="${1:-127.0.0.1}"
  case "${DB_MODE:-local}" in
    container)
      printf '%s' "mysql"
      return
      ;;
    rds)
      printf '%s' "$host"
      return
      ;;
  esac
  if [ -f /.dockerenv ] && [ -n "${HOST_DB_HOST:-}" ] && is_loopback_db_host "$host"; then
    host="$HOST_DB_HOST"
  fi
  printf '%s' "$host"
}

export_docker_db_hosts() {
  [ -f /.dockerenv ] || return 0
  export DB_HOST="$(docker_db_host "${DB_HOST:-127.0.0.1}")"
  export TENANT_DB_HOST="$(docker_db_host "${TENANT_DB_HOST:-${DB_HOST}}")"
}

# mysqladmin prints "mysqld is alive" to stdout — must silence when used in $(...) or if tests.
mysql_ping_silent() {
  local host="$1"
  local user="${2:-${DB_USERNAME:-admin}}"
  local port="${3:-${DB_PORT:-3306}}"
  local pass="${4:-${DB_PASSWORD:-}}"
  local ping=()
  if [ -n "${DB_SOCKET:-}" ] && [ -S "$DB_SOCKET" ]; then
    ping=(mysqladmin ping -S"$DB_SOCKET" -u"$user")
  else
    ping=(mysqladmin ping -h"$host" -P"$port" -u"$user")
  fi
  if [ -n "$pass" ]; then
    ping+=(-p"$pass")
  fi
  "${ping[@]}" --silent >/dev/null 2>&1
}

prepare_composer_env() {
  export COMPOSER_ALLOW_SUPERUSER=1
  export COMPOSER_MEMORY_LIMIT="${COMPOSER_MEMORY_LIMIT:--1}"
  export COMPOSER_MAX_PARALLEL_HTTP=1
  export COMPOSER_PROCESS_TIMEOUT=0
  export GIT_CONFIG_GLOBAL="${GIT_CONFIG_GLOBAL:-/tmp/composer-gitconfig}"
  git config --file "$GIT_CONFIG_GLOBAL" --add safe.directory '*' 2>/dev/null || true
  git config --file "$GIT_CONFIG_GLOBAL" --add safe.directory "$(pwd)" 2>/dev/null || true
  git config --global --add safe.directory '*' 2>/dev/null || true
  local app_dir
  app_dir="$(pwd)"
  if [ -d "$app_dir/.git" ]; then
    git config --global --add safe.directory "$app_dir" 2>/dev/null || true
    git config --file "$GIT_CONFIG_GLOBAL" --add safe.directory "$app_dir" 2>/dev/null || true
  fi
}

restore_composer_lock_from_git() {
  composer_lock_needs_php84 || return 0
  [ -d .git ] || return 1
  prepare_composer_env
  echo "==> composer.lock requires PHP 8.4+ — trying git restore..."
  git fetch origin 2>/dev/null || true
  local ref
  for ref in "origin/main" "origin/master" "origin/$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"; do
    if git cat-file -e "$ref:composer.lock" 2>/dev/null; then
      if git checkout "$ref" -- composer.lock composer.json 2>/dev/null; then
        if ! composer_lock_needs_php84; then
          echo "==> Restored composer.lock from $ref"
          return 0
        fi
      fi
    fi
  done
  return 1
}

composer_lock_needs_php84() {
  [ -f composer.lock ] || return 1
  grep -q '"name": "symfony/clock"' composer.lock 2>/dev/null || return 1
  grep -A3 '"name": "symfony/clock"' composer.lock | grep -qE '"version": "v8\.' 2>/dev/null
}

composer_install_app() {
  local production="${1:-0}"
  prepare_composer_env
  local php_ver
  php_ver="$(php -r 'echo PHP_VERSION;')"
  composer config platform.php "$php_ver" 2>/dev/null || true
  composer config --global process-timeout 0 2>/dev/null || true
  composer config --global cache-dir /tmp/composer-cache 2>/dev/null || true

  if composer_lock_needs_php84 && php -r 'exit(version_compare(PHP_VERSION, "8.4.1", "<") ? 0 : 1);'; then
    restore_composer_lock_from_git || true
  fi

  if composer_lock_needs_php84 && php -r 'exit(version_compare(PHP_VERSION, "8.4.1", "<") ? 0 : 1);'; then
    echo "FATAL: composer.lock requires Symfony 8.1 / PHP 8.4+ but container runs PHP ${php_ver}"
    echo "On the server (as ubuntu), run:"
    echo "  cd /var/www/B2B_CRM && ./docker/scripts/fix-master-composer-lock.sh"
    echo "Or manually:"
    echo "  cd /var/www/b2b-master-datamanagement && git fetch origin && git checkout origin/main -- composer.lock composer.json"
    exit 1
  fi

  local args=(install --prefer-dist --no-interaction --no-progress)
  if [ "$production" = "1" ]; then
    args+=(--no-dev --optimize-autoloader)
  fi

  local attempt
  for attempt in 1 2 3; do
    echo "==> composer install attempt ${attempt}/3 (parallel downloads disabled)..."
    if composer "${args[@]}"; then
      return 0
    fi
    echo "==> composer install failed on attempt ${attempt}"
    find vendor/composer -maxdepth 1 -name 'tmp-*' -delete 2>/dev/null || true
    composer clear-cache 2>/dev/null || true
  done
  return 1
}
