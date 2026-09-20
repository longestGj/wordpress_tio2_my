#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT=${APP_ROOT:-/opt/tio2products}

validate_release_sha() { [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]; }
[[ $# -eq 2 ]] || { echo 'usage: receive-release.sh RELEASE_SHA ARCHIVE' >&2; exit 64; }
release=$1
archive=$2
validate_release_sha "$release" || { echo 'Invalid release SHA' >&2; exit 64; }

archive_real=$(realpath -e -- "$archive") || exit 66
[[ -f "$archive_real" && "$archive_real" == "$APP_ROOT/incoming/"* ]] || {
  echo 'Release archive must be a regular file under the incoming directory' >&2
  exit 65
}
[[ "$(basename "$archive_real")" == "$release.tar.gz" ]] || {
  echo 'Release archive name does not match the release SHA' >&2
  exit 65
}

temporary=$(mktemp -d "$APP_ROOT/incoming/.release-${release}.XXXXXX")
bundle="$temporary/bundle"
members="$temporary/members.txt"
cleanup_release() { rm -rf -- "$temporary"; }
trap cleanup_release EXIT
mkdir "$bundle"

tar --quoting-style=literal -tzf "$archive_real" >"$members"
while IFS= read -r member || [[ -n "$member" ]]; do
  [[ -n "$member" && "$member" != /* && "$member" != *\\* ]] || {
    echo 'Release archive contains an unsafe member path' >&2
    exit 65
  }
  [[ ! "$member" =~ (^|/)\.\.(/|$) ]] || {
    echo 'Release archive contains path traversal' >&2
    exit 65
  }
done <"$members"

LC_ALL=C tar -tvzf "$archive_real" | awk 'substr($1,1,1) !~ /^[-d]$/ { exit 1 }' || {
  echo 'Release archive may contain only regular files and directories' >&2
  exit 65
}
tar -xzf "$archive_real" --directory "$bundle" --no-same-owner --no-same-permissions
[[ -f "$bundle/scripts/deploy.sh" ]] || { echo 'Release archive is missing scripts/deploy.sh' >&2; exit 66; }

bash "$bundle/scripts/deploy.sh" "$release" "$bundle"
rm -f -- "$archive_real"
