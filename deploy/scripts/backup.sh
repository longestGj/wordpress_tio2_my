#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

usage() { echo 'usage: backup.sh --release RELEASE_SHA | --scheduled' >&2; exit 64; }

release=''
scheduled=0
case "${1:-}" in
  --release)
    [[ $# -eq 2 ]] || usage
    validate_release_sha "$2" || usage
    release=$2
    ;;
  --scheduled)
    [[ $# -eq 1 ]] || usage
    scheduled=1
    ;;
  *) usage ;;
esac

[[ "${TIO2_LOCK_HELD:-0}" == 1 ]] || acquire_deploy_lock
load_server_env
if (( scheduled == 1 )); then
  current=$(current_release_dir) || { echo 'No current release is installed' >&2; exit 66; }
  release=$(cat "$current/release-sha")
  validate_release_sha "$release" || exit 65
fi
dir=$(require_release_dir "$release")
timestamp=$(date -u +%Y%m%dT%H%M%SZ)
backup="$APP_ROOT/backups/${timestamp}-${release:0:12}"
mkdir -p "$APP_ROOT/backups"
mkdir "$backup"
complete=0

mark_failed() {
  local status=$?
  if (( complete == 0 )) && [[ -d "$backup" ]]; then
    mv -- "$backup" "${backup}.failed" || true
  fi
  exit "$status"
}
trap mark_failed EXIT

compose "$dir" exec -T db sh -ec \
  'exec mariadb-dump --user=root --password="$MARIADB_ROOT_PASSWORD" --single-transaction --quick --lock-tables=false "$MARIADB_DATABASE"' \
  >"$backup/database.sql"
compose "$dir" exec -T wordpress tar -C /var/www/html/wp-content -czf - uploads >"$backup/uploads.tar.gz"

image=$(cat "$dir/image-ref")
printf '{"created_at":"%s","release_sha":"%s","image":"%s"}\n' \
  "$timestamp" "$release" "$image" >"$backup/metadata.json"
(cd "$backup" && sha256sum database.sql uploads.tar.gz metadata.json >SHA256SUMS)

test -s "$backup/database.sql"
grep -qE 'MariaDB dump|MySQL dump' "$backup/database.sql"
test -s "$backup/uploads.tar.gz"
tar -tzf "$backup/uploads.tar.gz" >/dev/null
(cd "$backup" && sha256sum -c SHA256SUMS)

complete=1
trap - EXIT
prune_backups
log_event info "backup_succeeded release=$release directory=$(basename "$backup")"
printf '%s\n' "$backup"
