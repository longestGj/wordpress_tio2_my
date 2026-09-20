#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

[[ $# -eq 2 ]] || { echo 'usage: deploy.sh RELEASE_SHA BUNDLE_DIR' >&2; exit 64; }
release=$1
validate_release_sha "$release" || { echo 'Invalid release SHA' >&2; exit 64; }
bundle=$(realpath -e "$2") || exit 66
[[ -d "$bundle" ]] || exit 66
for required in compose.production.yaml Caddyfile scripts/common.sh scripts/backup.sh scripts/bootstrap.sh scripts/healthcheck.sh scripts/deploy.sh scripts/rollback.sh; do
  [[ -f "$bundle/$required" ]] || { echo "Bundle file is missing: $required" >&2; exit 66; }
done

acquire_deploy_lock
load_server_env
mkdir -p "$APP_ROOT/releases" "$APP_ROOT/shared/state" "$APP_ROOT/logs"
dir=$(release_dir "$release")
image="$IMAGE_REPOSITORY:$release"

if [[ -d "$dir" ]]; then
  [[ "$(cat "$dir/release-sha")" == "$release" && "$(cat "$dir/image-ref")" == "$image" ]] || exit 65
else
  staging="$APP_ROOT/releases/.staging-${release}-$$"
  trap 'rm -rf -- "${staging:-}"' EXIT
  install -d -m 750 "$staging/scripts"
  install -m 640 "$bundle/compose.production.yaml" "$staging/compose.production.yaml"
  install -m 640 "$bundle/Caddyfile" "$staging/Caddyfile"
  install -m 750 "$bundle/scripts/"*.sh "$staging/scripts/"
  printf '%s\n' "$release" >"$staging/release-sha"
  printf '%s\n' "$image" >"$staging/image-ref"
  mv -- "$staging" "$dir"
  trap - EXIT
fi

compose "$dir" config --quiet
old_dir=''
old_release=''
if old_dir=$(current_release_dir 2>/dev/null); then
  old_release=$(cat "$old_dir/release-sha")
fi
mutated=0
succeeded=0

finish_deploy() {
  local status=$?
  (( succeeded == 1 )) && return 0
  set +e
  log_event error "deployment_failed release=$release status=$status"
  if (( mutated == 1 )); then
    if [[ -n "$old_dir" ]]; then
      compose "$old_dir" up -d --wait
      if "$old_dir/scripts/healthcheck.sh" "$old_dir"; then
        log_event warning "rollback_succeeded release=$release restored=$old_release"
      else
        log_event error "rollback_failed release=$release target=$old_release"
      fi
    else
      compose "$dir" down --remove-orphans
      log_event error "first_deploy_failed release=$release"
    fi
  fi
  exit "$status"
}
trap finish_deploy EXIT

if [[ -n "$old_release" ]]; then
  TIO2_LOCK_HELD=1 "$SCRIPT_DIR/backup.sh" --release "$old_release" >/dev/null
fi

compose "$dir" pull
mutated=1
compose "$dir" up -d --wait db wordpress
"$SCRIPT_DIR/bootstrap.sh" "$dir"
compose "$dir" up -d --wait caddy
"$SCRIPT_DIR/healthcheck.sh" "$dir"

[[ -n "$old_dir" ]] && atomic_link "$old_dir" "$APP_ROOT/previous"
atomic_link "$dir" "$APP_ROOT/current"
printf '%s\n' "$release" >"$APP_ROOT/shared/state/current-sha.tmp.$$"
mv -Tf "$APP_ROOT/shared/state/current-sha.tmp.$$" "$APP_ROOT/shared/state/current-sha"
prune_releases
log_event info "deployment_succeeded release=$release previous=${old_release:-none}"
succeeded=1
trap - EXIT
