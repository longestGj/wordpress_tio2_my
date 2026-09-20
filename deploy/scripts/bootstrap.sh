#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

[[ $# -eq 1 ]] || { echo 'usage: bootstrap.sh RELEASE_DIR' >&2; exit 64; }
dir=$(realpath -e "$1")
[[ "$dir" == "$APP_ROOT/releases/"* ]] || exit 65
load_server_env
: "${WP_ADMIN_USER:?WP_ADMIN_USER is required}"
: "${WP_ADMIN_PASSWORD:?WP_ADMIN_PASSWORD is required}"
: "${WP_ADMIN_EMAIL:?WP_ADMIN_EMAIL is required}"

wp() { compose "$dir" exec -T wordpress wp --allow-root "$@"; }

ready=0
for _ in $(seq 1 60); do
  if wp core version >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
(( ready == 1 )) || { echo 'WordPress filesystem did not become ready' >&2; exit 1; }

if ! wp core is-installed >/dev/null 2>&1; then
  printf '%s\n' "$WP_ADMIN_PASSWORD" | wp core install \
    --url="$PUBLIC_URL" --title='TiO2 Products' --admin_user="$WP_ADMIN_USER" \
    --admin_email="$WP_ADMIN_EMAIL" --skip-email --prompt=admin_password
fi

wp option update home "$PUBLIC_URL" --quiet
wp option update siteurl "$PUBLIC_URL" --quiet
wp option update timezone_string Asia/Shanghai --quiet
wp option update permalink_structure '/%postname%/' --quiet
wp option update default_comment_status closed --quiet
wp option update default_ping_status closed --quiet
wp option update blog_public 0 --quiet
wp theme activate tio2-malaysia --quiet
wp plugin activate tio2-content --quiet
wp eval-file /opt/tio2/bin/bootstrap-production.php
wp rewrite flush --hard --quiet
wp eval 'tio2_content(); echo "content-readable\n";' | grep -q '^content-readable$'
