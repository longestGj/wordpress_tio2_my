#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

[[ $# -eq 1 ]] || { echo 'usage: rollback.sh RELEASE_SHA' >&2; exit 64; }
target_release=$1
validate_release_sha "$target_release" || exit 64
acquire_deploy_lock
load_server_env
target_dir=$(require_release_dir "$target_release")
old_dir=$(current_release_dir) || { echo 'No current release is installed' >&2; exit 66; }
old_release=$(cat "$old_dir/release-sha")

if [[ "$old_release" == "$target_release" ]]; then
  "$SCRIPT_DIR/healthcheck.sh" "$old_dir"
  exit 0
fi

TIO2_LOCK_HELD=1 "$SCRIPT_DIR/backup.sh" --release "$old_release" >/dev/null
mutated=0
succeeded=0

finish_rollback() {
  local status=$?
  (( succeeded == 1 )) && return 0
  set +e
  if (( mutated == 1 )); then
    compose "$old_dir" up -d --wait
    "$old_dir/scripts/healthcheck.sh" "$old_dir" || true
  fi
  log_event error "manual_rollback_failed from=$old_release target=$target_release status=$status"
  exit "$status"
}
trap finish_rollback EXIT

compose "$target_dir" pull
mutated=1
compose "$target_dir" up -d --wait db wordpress
"$target_dir/scripts/bootstrap.sh" "$target_dir"
compose "$target_dir" up -d --wait caddy
"$target_dir/scripts/healthcheck.sh" "$target_dir"

atomic_link "$old_dir" "$APP_ROOT/previous"
atomic_link "$target_dir" "$APP_ROOT/current"
printf '%s\n' "$target_release" >"$APP_ROOT/shared/state/current-sha.tmp.$$"
mv -Tf "$APP_ROOT/shared/state/current-sha.tmp.$$" "$APP_ROOT/shared/state/current-sha"
log_event warning "manual_rollback_succeeded from=$old_release target=$target_release"
succeeded=1
trap - EXIT
