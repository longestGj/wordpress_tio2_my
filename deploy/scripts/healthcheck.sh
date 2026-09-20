#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
# shellcheck source=common.sh
source "$SCRIPT_DIR/common.sh"

[[ $# -eq 1 ]] || { echo 'usage: healthcheck.sh RELEASE_DIR' >&2; exit 64; }
dir=$(realpath -e "$1")
[[ "$dir" == "$APP_ROOT/releases/"* ]] || exit 65
load_server_env
release=$(cat "$dir/release-sha")
validate_release_sha "$release" || exit 65
public=${PUBLIC_URL%/}
public_host=${public#*://}
public_host=${public_host%%/*}

for service in db wordpress caddy; do
  container=$(compose "$dir" ps -q "$service")
  [[ -n "$container" ]] || { echo "$service container is missing" >&2; exit 1; }
  [[ "$(docker inspect --format '{{.State.Running}}' "$container")" == true ]] || { echo "$service is not running" >&2; exit 1; }
  health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container")
  [[ "$health" == healthy || "$health" == none ]] || { echo "$service is not healthy" >&2; exit 1; }
done

compose "$dir" exec -T db healthcheck.sh --connect --innodb_initialized >/dev/null
compose "$dir" exec -T wordpress wp --allow-root eval 'tio2_content();' >/dev/null
compose "$dir" exec -T -e "TIO2_HEALTH_HOST=$public_host" wordpress php -r '
  $context=stream_context_create(["http"=>["header"=>"Host: ".getenv("TIO2_HEALTH_HOST")."\r\n","ignore_errors"=>true,"timeout"=>10]]);
  $body=file_get_contents("http://127.0.0.1/",false,$context);
  $status=$http_response_header[0]??"";
  exit($body!==false && str_contains($status," 200 ") ? 0 : 1);
'

base=${HEALTHCHECK_BASE_URL:-$PUBLIC_URL}
base=${base%/}
state_dir="$APP_ROOT/shared/state/health-${release}-$$"
mkdir -p "$state_dir"
trap 'rm -rf -- "$state_dir"' EXIT
curl -fsS --max-time 30 -D "$state_dir/headers" -o "$state_dir/home.html" "$base/"
tr -d '\r' <"$state_dir/headers" >"$state_dir/headers.clean"
grep -q 'Malaysia Titanium Dioxide for Industrial Buyers' "$state_dir/home.html"
grep -Fq "<link rel=\"canonical\" href=\"$public/\">" "$state_dir/home.html"
grep -Fq '<meta name="robots" content="noindex, nofollow">' "$state_dir/home.html"
grep -qi '^X-Site-Scope: tio2-my$' "$state_dir/headers.clean"
grep -qi "^X-Tio2-Release: $release$" "$state_dir/headers.clean"

if [[ "${HEALTHCHECK_MODE:-production}" == production ]]; then
  curl -sS --max-time 20 -D "$state_dir/http-redirect" -o /dev/null --max-redirs 0 http://tio2products.com/
  tr -d '\r' <"$state_dir/http-redirect" | grep -Eq '^HTTP/[^ ]+ (301|308) '
  tr -d '\r' <"$state_dir/http-redirect" | grep -Eiq '^location: https://tio2products.com/$'

  curl -sS --max-time 20 -D "$state_dir/www-redirect" -o /dev/null --max-redirs 0 'https://www.tio2products.com/health-path?source=deploy'
  tr -d '\r' <"$state_dir/www-redirect" | grep -Eq '^HTTP/[^ ]+ (301|308) '
  tr -d '\r' <"$state_dir/www-redirect" | grep -Eiq '^location: https://tio2products.com/health-path\?source=deploy$'

  printf '' | openssl s_client -connect tio2products.com:443 -servername tio2products.com \
    -verify_hostname tio2products.com -verify_return_error >"$state_dir/tls" 2>&1
  sed -n '/-----BEGIN CERTIFICATE-----/,/-----END CERTIFICATE-----/p' "$state_dir/tls" >"$state_dir/certificate.pem"
  openssl x509 -in "$state_dir/certificate.pem" -checkend 86400 -noout
fi

log_event info "healthcheck_succeeded release=$release"
