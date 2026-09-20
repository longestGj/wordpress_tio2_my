#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"
export TIO2_SOURCE_ONLY=1
export APP_ROOT
APP_ROOT=$(mktemp -d)
trap 'rm -rf "$APP_ROOT"' EXIT

source deploy/scripts/common.sh

valid=0123456789abcdef0123456789abcdef01234567
validate_release_sha "$valid"
for invalid in '' abc "${valid^^}" "../$valid" "$valid;id" "$valid/child"; do
  if validate_release_sha "$invalid"; then
    echo "invalid release accepted: $invalid" >&2
    exit 1
  fi
done
[[ "$(release_dir "$valid")" == "$APP_ROOT/releases/$valid" ]]
if release_dir '../escape' >/dev/null 2>&1; then
  echo 'release path traversal was accepted' >&2
  exit 1
fi

mkdir -p "$APP_ROOT/releases/$valid" "$APP_ROOT/shared/state" "$APP_ROOT/backups"
printf '%s\n' "$valid" >"$APP_ROOT/releases/$valid/release-sha"
printf 'ghcr.io/longestgj/wordpress_tio2_my:%s\n' "$valid" >"$APP_ROOT/releases/$valid/image-ref"
[[ "$(require_release_dir "$valid")" == "$APP_ROOT/releases/$valid" ]]
printf 'malicious.example/image:%s\n' "$valid" >"$APP_ROOT/releases/$valid/image-ref"
if require_release_dir "$valid" >/dev/null 2>&1; then
  echo 'unexpected release image repository was accepted' >&2
  exit 1
fi
printf 'ghcr.io/longestgj/wordpress_tio2_my:%s\n' "$valid" >"$APP_ROOT/releases/$valid/image-ref"
atomic_link "$APP_ROOT/releases/$valid" "$APP_ROOT/current"
[[ "$(readlink "$APP_ROOT/current")" == "$APP_ROOT/releases/$valid" ]]
mkdir "$APP_ROOT/outside-release"
if atomic_link "$APP_ROOT/outside-release" "$APP_ROOT/escape-link" >/dev/null 2>&1; then
  echo 'atomic link accepted a target outside releases' >&2
  exit 1
fi
if atomic_link "$APP_ROOT/releases/$valid" "$APP_ROOT/shared/.env" >/dev/null 2>&1; then
  echo 'atomic link accepted a protected destination' >&2
  exit 1
fi

(
  source deploy/scripts/common.sh
  acquire_deploy_lock
  date +%s%N >"$APP_ROOT/first-acquired"
  sleep 2
  date +%s%N >"$APP_ROOT/first-released"
) &
first_pid=$!
for _ in $(seq 1 50); do
  [[ -f "$APP_ROOT/first-acquired" ]] && break
  sleep 0.05
done
(
  source deploy/scripts/common.sh
  acquire_deploy_lock
  date +%s%N >"$APP_ROOT/second-acquired"
) &
second_pid=$!
wait "$first_pid" "$second_pid"
[[ "$(cat "$APP_ROOT/second-acquired")" -ge "$(cat "$APP_ROOT/first-released")" ]]

for index in $(seq 0 9); do
  name=$(printf '20260920T%06dZ-%012x' "$index" "$index")
  mkdir "$APP_ROOT/backups/$name"
done
mkdir "$APP_ROOT/backups/20260920T999999Z-000000000000.failed"
sentinel=$(dirname "$APP_ROOT")/tio2-retention-sentinel-$$
mkdir "$sentinel"
prune_backups
[[ "$(find "$APP_ROOT/backups" -mindepth 1 -maxdepth 1 -type d -printf '.' | wc -c)" -eq 8 ]]
[[ -d "$sentinel" ]]
rmdir "$sentinel"

log_event info 'contract message'
grep -q '"level":"info"' "$APP_ROOT/logs/deploy.log"
grep -q '"message":"contract message"' "$APP_ROOT/logs/deploy.log"

echo 'deployment script validation, locking and retention contracts passed'
