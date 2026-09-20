#!/usr/bin/env bash
set -euo pipefail

case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS2_ARG_CONV_EXCL='/opt;/workspace' ;;
esac

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

VALID_SHA=0123456789abcdef0123456789abcdef01234567
IMAGE="tio2-production-test:${VALID_SHA}"
PROJECT="tio2-bootstrap-$$"
COMPOSE_FILE=.runtime/bootstrap-compose.yaml

cleanup() {
  docker compose -p "$PROJECT" -f "$COMPOSE_FILE" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

test -f scripts/bootstrap-production.php
docker image inspect "$IMAGE" >/dev/null 2>&1 || bash tests/image-contract.sh
mkdir -p .runtime
cat >"$COMPOSE_FILE" <<YAML
services:
  db:
    image: mariadb:11.4@sha256:4f1d8d202fcf7bcb3902f63af09f9c1a050c2922a89652f22abaec0d4f015e83
    environment:
      MARIADB_DATABASE: wordpress
      MARIADB_USER: wordpress
      MARIADB_PASSWORD: bootstrap-db-password
      MARIADB_ROOT_PASSWORD: bootstrap-root-password
    healthcheck:
      test: [CMD, healthcheck.sh, --connect, --innodb_initialized]
      interval: 2s
      timeout: 5s
      retries: 40
    volumes:
      - db:/var/lib/mysql
  wordpress:
    image: $IMAGE
    depends_on:
      db:
        condition: service_healthy
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: bootstrap-db-password
      TIO2_PUBLIC_URL: https://tio2products.com
      TIO2_SITE_SCOPE: tio2-my
      TIO2_CONTENT_INIT_VERSION: home-v1
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_ENVIRONMENT_TYPE', 'production');
        define('TIO2_PUBLIC_URL', getenv('TIO2_PUBLIC_URL'));
        define('TIO2_SITE_SCOPE', getenv('TIO2_SITE_SCOPE'));
        define('TIO2_RELEASE', getenv('TIO2_RELEASE'));
        define('DISALLOW_FILE_EDIT', true);
        define('WP_AUTO_UPDATE_CORE', false);
    volumes:
      - uploads:/var/www/html/wp-content/uploads
volumes:
  db:
  uploads:
YAML

compose() { docker compose -p "$PROJECT" -f "$COMPOSE_FILE" "$@"; }
wp() { compose exec -T wordpress wp --allow-root "$@"; }

compose up -d
for _ in $(seq 1 60); do
  if wp core version >/dev/null 2>&1; then break; fi
  sleep 2
done
wp core version >/dev/null
wp core install --url=https://tio2products.com --title='TiO2 Products' --admin_user=bootstrap-admin --admin_password=bootstrap-admin-password --admin_email=bootstrap@example.test --skip-email

if compose exec -T -e TIO2_CONTENT_INIT_VERSION='../invalid' wordpress wp --allow-root eval-file /opt/tio2/bin/bootstrap-production.php >/dev/null 2>&1; then
  echo 'invalid content initialization version was accepted' >&2
  exit 1
fi

first=$(wp eval-file /opt/tio2/bin/bootstrap-production.php)
echo "$first" | grep -q '"content":"imported"'
test "$(wp option get tio2_content_init_version)" = 'home-v1'
test "$(wp option get tio2_products_migration_version)" = '2'
product_page_id=$(wp eval '$page=get_page_by_path("products",OBJECT,"page");echo $page?(int)$page->ID:0;')
test "$product_page_id" -gt 0
test "$(wp post get "$product_page_id" --field=post_status)" = 'publish'
test "$(wp post meta get "$product_page_id" _tio2_page_id)" = 'PRODUCT-000'
test "$(wp post meta get "$product_page_id" _tio2_site_scope)" = 'tio2-my'
test "$(wp post meta get "$product_page_id" _tio2_managed_page)" = '1'
test "$(wp post meta get "$product_page_id" _wp_page_template)" = 'page-products.php'
media_before=$(wp post list --post_type=attachment --format=count)

wp eval '$content=get_option("tio2_content");$content["fields"]["hero.heading.1"]="Preserved production edit";update_option("tio2_content",$content,false);'
wp eval '$content=get_option("tio2_products_content");$content["fields"]["directory.grade.1.summary"]="Preserved Products production edit";update_option("tio2_products_content",$content,false);'
second=$(wp eval-file /opt/tio2/bin/bootstrap-production.php)
echo "$second" | grep -q '"content":"already-initialized"'
test "$(wp eval 'echo get_option("tio2_content")["fields"]["hero.heading.1"];')" = 'Preserved production edit'
test "$(wp eval 'echo get_option("tio2_products_content")["fields"]["directory.grade.1.summary"];')" = 'Preserved Products production edit'
test "$(wp eval '$page=get_page_by_path("products",OBJECT,"page");echo $page?(int)$page->ID:0;')" = "$product_page_id"
test "$(wp post list --post_type=attachment --format=count)" = "$media_before"

wp option delete tio2_content_init_version >/dev/null
adopted=$(wp eval-file /opt/tio2/bin/bootstrap-production.php)
echo "$adopted" | grep -q '"content":"preserved-existing"'
test "$(wp option get tio2_content_init_version)" = 'adopted-existing'
test "$(wp eval 'echo get_option("tio2_content")["fields"]["hero.heading.1"];')" = 'Preserved production edit'
test "$(wp eval 'echo get_option("tio2_products_content")["fields"]["directory.grade.1.summary"];')" = 'Preserved Products production edit'
test "$(wp eval '$page=get_page_by_path("products",OBJECT,"page");echo $page?(int)$page->ID:0;')" = "$product_page_id"
test "$(wp post list --post_type=attachment --format=count)" = "$media_before"

echo 'production bootstrap idempotency contract passed'
