#!/usr/bin/env bash
set -Eeuo pipefail
set +x

[[ ${EUID:-$(id -u)} -eq 0 ]] || { echo 'Run configure-env.sh as root' >&2; exit 77; }
id deploy >/dev/null 2>&1 || { echo 'Provision the deploy user first' >&2; exit 66; }

APP_ROOT=${APP_ROOT:-/opt/tio2products}
target="$APP_ROOT/shared/.env"
[[ ! -e "$target" ]] || { echo 'Production environment already exists; refusing to rotate live secrets' >&2; exit 73; }
install -d -m 750 -o deploy -g deploy "$APP_ROOT/shared"
umask 077

read -r -s -p 'WordPress administrator username: ' WP_ADMIN_USER
printf '\n'
read -r -s -p 'WordPress administrator password (16+ safe characters): ' WP_ADMIN_PASSWORD
printf '\n'
read -r -s -p 'Administrator and ACME email: ' WP_ADMIN_EMAIL
printf '\n'

[[ "$WP_ADMIN_USER" =~ ^[A-Za-z0-9._-]{3,60}$ ]] || { echo 'Invalid WordPress administrator username' >&2; exit 65; }
[[ ${#WP_ADMIN_PASSWORD} -ge 16 && ${#WP_ADMIN_PASSWORD} -le 128 ]] || { echo 'Administrator password must contain 16 to 128 characters' >&2; exit 65; }
[[ "$WP_ADMIN_PASSWORD" =~ ^[A-Za-z0-9._@%+=:,/-]+$ ]] || { echo 'Administrator password contains unsupported characters' >&2; exit 65; }
[[ "$WP_ADMIN_EMAIL" =~ ^[A-Za-z0-9.!#$%\&\'*+/=?^_\`{|}~-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]] || { echo 'Invalid email address' >&2; exit 65; }
ACME_EMAIL=$WP_ADMIN_EMAIL

generate_secret() { openssl rand -hex 32; }
DB_PASSWORD=$(generate_secret)
DB_ROOT_PASSWORD=$(generate_secret)
WORDPRESS_AUTH_KEY=$(generate_secret)
WORDPRESS_SECURE_AUTH_KEY=$(generate_secret)
WORDPRESS_LOGGED_IN_KEY=$(generate_secret)
WORDPRESS_NONCE_KEY=$(generate_secret)
WORDPRESS_AUTH_SALT=$(generate_secret)
WORDPRESS_SECURE_AUTH_SALT=$(generate_secret)
WORDPRESS_LOGGED_IN_SALT=$(generate_secret)
WORDPRESS_NONCE_SALT=$(generate_secret)

for secret in \
  "$DB_PASSWORD" "$DB_ROOT_PASSWORD" "$WORDPRESS_AUTH_KEY" "$WORDPRESS_SECURE_AUTH_KEY" \
  "$WORDPRESS_LOGGED_IN_KEY" "$WORDPRESS_NONCE_KEY" "$WORDPRESS_AUTH_SALT" \
  "$WORDPRESS_SECURE_AUTH_SALT" "$WORDPRESS_LOGGED_IN_SALT" "$WORDPRESS_NONCE_SALT"; do
  [[ "$secret" =~ ^[0-9a-f]{64}$ ]] || { echo 'Secret generation failed' >&2; exit 70; }
done

temporary=$(mktemp "$APP_ROOT/shared/.env.tmp.XXXXXX")
cleanup_environment() { rm -f -- "$temporary"; }
trap cleanup_environment EXIT
cat >"$temporary" <<EOF
COMPOSE_PROJECT_NAME=tio2products
VOLUME_PREFIX=tio2products
APP_IMAGE=
RELEASE_SHA=
DB_NAME=wordpress
DB_USER=wordpress
DB_PASSWORD=$DB_PASSWORD
DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD
WORDPRESS_AUTH_KEY=$WORDPRESS_AUTH_KEY
WORDPRESS_SECURE_AUTH_KEY=$WORDPRESS_SECURE_AUTH_KEY
WORDPRESS_LOGGED_IN_KEY=$WORDPRESS_LOGGED_IN_KEY
WORDPRESS_NONCE_KEY=$WORDPRESS_NONCE_KEY
WORDPRESS_AUTH_SALT=$WORDPRESS_AUTH_SALT
WORDPRESS_SECURE_AUTH_SALT=$WORDPRESS_SECURE_AUTH_SALT
WORDPRESS_LOGGED_IN_SALT=$WORDPRESS_LOGGED_IN_SALT
WORDPRESS_NONCE_SALT=$WORDPRESS_NONCE_SALT
WP_ADMIN_USER=$WP_ADMIN_USER
WP_ADMIN_PASSWORD=$WP_ADMIN_PASSWORD
WP_ADMIN_EMAIL=$WP_ADMIN_EMAIL
PUBLIC_URL=https://tio2products.com
SITE_SCOPE=tio2-my
TIO2_CONTENT_INIT_VERSION=home-v1
ACME_EMAIL=$ACME_EMAIL
EOF
chown deploy:deploy "$temporary"
chmod 600 "$temporary"
mv -f -- "$temporary" "$target"
trap - EXIT

echo "Production environment installed at $target."
