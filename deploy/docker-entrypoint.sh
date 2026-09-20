#!/bin/sh
set -eu

sync_owned_code() {
  source=$1
  target=$2
  staging="${target}.tio2-new.$$"

  mkdir -p "$(dirname "$target")"
  rm -rf -- "$staging"
  cp -a -- "$source" "$staging"
  chown -R www-data:www-data "$staging"
  rm -rf -- "$target"
  mv -- "$staging" "$target"
}

# The upstream image declares /var/www/html as a volume and intentionally
# preserves existing plugin/theme directories. Replace only this site's owned
# code before Apache starts; uploads, WordPress core and third-party code stay
# untouched.
sync_owned_code /usr/src/wordpress/wp-content/themes/tio2-malaysia /var/www/html/wp-content/themes/tio2-malaysia
sync_owned_code /usr/src/wordpress/wp-content/plugins/tio2-content /var/www/html/wp-content/plugins/tio2-content

exec /usr/local/bin/docker-entrypoint.sh "$@"
