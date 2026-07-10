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

# Named volume mount points cannot be removed — only their contents.
clear_mount_dir() {
  local dir="$1"
  [ -d "$dir" ] || return 0
  find "$dir" -mindepth 1 -delete 2>/dev/null || \
    rm -rf "${dir:?}"/* "${dir:?}"/.[!.]* 2>/dev/null || true
}

# Docker local mode: 127.0.0.1 is the container loopback — use host.docker.internal.
docker_db_host() {
  local host="${1:-127.0.0.1}"
  if [ -f /.dockerenv ] && [ "${DB_MODE:-local}" = "local" ]; then
    case "$host" in
      127.0.0.1|localhost|::1) host="host.docker.internal" ;;
    esac
  fi
  printf '%s' "$host"
}

# mysqladmin prints "mysqld is alive" to stdout — must silence when used in $(...) or if tests.
mysql_ping_silent() {
  local host="$1"
  local user="${2:-${DB_USERNAME:-admin}}"
  local port="${3:-${DB_PORT:-3306}}"
  local pass="${4:-${DB_PASSWORD:-}}"
  local ping=(mysqladmin ping -h"$host" -P"$port" -u"$user")
  if [ -n "$pass" ]; then
    ping+=(-p"$pass")
  fi
  "${ping[@]}" --silent >/dev/null 2>&1
}
