#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT=${APP_ROOT:-/opt/tio2products}
IMAGE_REPOSITORY=${IMAGE_REPOSITORY:-ghcr.io/longestgj/wordpress_tio2_my}

validate_release_sha() {
  [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]
}

release_dir() {
  validate_release_sha "${1:-}" || return 64
  printf '%s/releases/%s\n' "$APP_ROOT" "$1"
}

require_release_dir() {
  local dir
  dir=$(release_dir "${1:-}") || return
  [[ -d "$dir" && -f "$dir/release-sha" && -f "$dir/image-ref" ]] || return 66
  [[ "$(cat "$dir/release-sha")" == "$1" ]] || return 65
  [[ "$(cat "$dir/image-ref")" == "$IMAGE_REPOSITORY:$1" ]] || return 65
  printf '%s\n' "$dir"
}

load_server_env() {
  local env_file="$APP_ROOT/shared/.env" mode
  [[ -f "$env_file" ]] || { echo "Production environment file is missing" >&2; return 66; }
  mode=$(stat -c '%a' "$env_file")
  [[ "$mode" == 600 ]] || { echo "Production environment file must have mode 600" >&2; return 65; }
  set -a
  # shellcheck disable=SC1090
  source "$env_file"
  set +a
  : "${PUBLIC_URL:?PUBLIC_URL is required}"
  : "${SITE_SCOPE:?SITE_SCOPE is required}"
}

compose() {
  local dir="$1" image release
  shift
  image=$(cat "$dir/image-ref")
  release=$(cat "$dir/release-sha")
  APP_IMAGE="$image" RELEASE_SHA="$release" \
    docker compose --project-directory "$dir" --env-file "$APP_ROOT/shared/.env" \
    -f "$dir/compose.production.yaml" "$@"
}

acquire_deploy_lock() {
  mkdir -p "$APP_ROOT/shared/state"
  exec 9>"$APP_ROOT/shared/state/deploy.lock"
  flock 9
}

atomic_link() {
  local target="$1" link="$2" temporary="${2}.tmp.$$" resolved
  resolved=$(realpath -e "$target") || return 66
  [[ "$resolved" == "$APP_ROOT/releases/"* ]] || return 64
  [[ "$link" == "$APP_ROOT/current" || "$link" == "$APP_ROOT/previous" ]] || return 64
  rm -f -- "$temporary"
  ln -s -- "$resolved" "$temporary"
  mv -Tf -- "$temporary" "$link"
}

log_event() {
  local level="$1" message="$2" timestamp
  mkdir -p "$APP_ROOT/logs"
  timestamp=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  message=${message//\\/\\\\}
  message=${message//\"/\\\"}
  message=${message//$'\n'/\\n}
  printf '{"time":"%s","level":"%s","message":"%s"}\n' "$timestamp" "$level" "$message" >>"$APP_ROOT/logs/deploy.log"
}

current_release_dir() {
  local resolved
  [[ -L "$APP_ROOT/current" ]] || return 1
  resolved=$(realpath -e "$APP_ROOT/current") || return 1
  [[ "$resolved" == "$APP_ROOT/releases/"* ]] || return 65
  printf '%s\n' "$resolved"
}

prune_backups() {
  local backup_root="$APP_ROOT/backups" name candidate resolved index=0
  local -a names=()
  mkdir -p "$backup_root"
  while IFS= read -r name; do names+=("$name"); done < <(
    find "$backup_root" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' \
      | grep -E '^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$' | sort -r || true
  )
  for name in "${names[@]}"; do
    index=$((index+1))
    (( index <= 7 )) && continue
    candidate="$backup_root/$name"
    resolved=$(realpath -e "$candidate") || continue
    [[ "$resolved" == "$backup_root/"* ]] || return 65
    rm -rf -- "$resolved"
  done
}

prune_releases() {
  local release_root="$APP_ROOT/releases" current='' previous='' name candidate resolved index=0
  local -a names=()
  [[ -L "$APP_ROOT/current" ]] && current=$(realpath -e "$APP_ROOT/current" || true)
  [[ -L "$APP_ROOT/previous" ]] && previous=$(realpath -e "$APP_ROOT/previous" || true)
  while IFS= read -r name; do names+=("$name"); done < <(
    find "$release_root" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' \
      | grep -E '^[0-9a-f]{40}$' | sort -r || true
  )
  for name in "${names[@]}"; do
    candidate="$release_root/$name"
    resolved=$(realpath -e "$candidate") || continue
    [[ "$resolved" == "$current" || "$resolved" == "$previous" ]] && continue
    index=$((index+1))
    (( index <= 3 )) && continue
    [[ "$resolved" == "$release_root/"* ]] || return 65
    rm -rf -- "$resolved"
  done
}
