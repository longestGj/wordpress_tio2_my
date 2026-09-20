#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

VALID_SHA=0123456789abcdef0123456789abcdef01234567
INVALID_SHA='../release;touch /tmp/tio2-invalid'
BUILD_DATE=2026-09-20T00:00:00Z
IMAGE="tio2-production-test:${VALID_SHA}"

mkdir -p .runtime
if docker build --build-arg "VCS_REF=$INVALID_SHA" --build-arg "BUILD_DATE=$BUILD_DATE" -t tio2-production-test:invalid . >.runtime/image-invalid.log 2>&1; then
  echo 'invalid VCS_REF was accepted' >&2
  exit 1
fi

docker build --build-arg "VCS_REF=$VALID_SHA" --build-arg "BUILD_DATE=$BUILD_DATE" -t "$IMAGE" .

test "$(docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.source"}}')" = 'https://github.com/longestGj/wordpress_tio2_my'
test "$(docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.revision"}}')" = "$VALID_SHA"
test "$(docker image inspect "$IMAGE" --format '{{index .Config.Labels "org.opencontainers.image.created"}}')" = "$BUILD_DATE"
test "$(docker image inspect "$IMAGE" --format '{{range .Config.Env}}{{println .}}{{end}}' | sed -n 's/^TIO2_RELEASE=//p')" = "$VALID_SHA"

docker run --rm "$IMAGE" sh -ec '
  command -v wp >/dev/null
  test -x /usr/local/bin/wp
  test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/style.css
  test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/page-products.php
  test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/assets/products.css
  test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/assets/products.js
  test -f /usr/src/wordpress/wp-content/plugins/tio2-content/tio2-content.php
  test -f /usr/src/wordpress/wp-content/plugins/tio2-content/includes/products.php
  test -f /usr/src/wordpress/wp-content/plugins/tio2-content/products-defaults.json
  test -f /usr/src/wordpress/wp-content/plugins/tio2-content/products-schema.json
  test -f /opt/tio2/content/initial-home.json
  test -f /opt/tio2/content/media/hero.png
  test -f /opt/tio2/bin/bootstrap-production.php
  test ! -e /opt/tio2/.git
  test ! -e /opt/tio2/.env
  test ! -e /opt/tio2/.runtime
  test ! -e /opt/tio2/docs
'

printf '%s\n' "$IMAGE" >.runtime/production-test-image
echo 'production image contract passed'
