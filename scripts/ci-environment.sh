#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

proxy_bypass='127.0.0.1,localhost,::1'
export NO_PROXY="${NO_PROXY:+$NO_PROXY,}$proxy_bypass"
export no_proxy="${no_proxy:+$no_proxy,}$proxy_bypass"

ENV_FILE=.runtime/ci.env
MARKER_FILE=''

case "$(uname -s)" in
  MINGW*|MSYS*) export MSYS2_ARG_CONV_EXCL='/workspace;/opt' ;;
esac

compose() { docker compose --env-file "$ENV_FILE" -f compose.yaml "$@"; }

load_ci_env() {
  [[ -f "$ENV_FILE" ]] || { echo 'CI environment is not initialized' >&2; exit 66; }
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
  MARKER_FILE=${TIO2_BOOTSTRAP_MARKER:-.runtime/bootstrap-${COMPOSE_PROJECT_NAME}}
}

up() {
  mkdir -p .runtime
  if [[ ! -f "$ENV_FILE" ]]; then
    port=${TIO2_CI_PORT:-$(python -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()')}
    project="tio2-ci-${GITHUB_RUN_ID:-$$}"
    project=${project,,}
    umask 077
    cat >"$ENV_FILE" <<EOF
COMPOSE_PROJECT_NAME=$project
TIO2_LOCAL_PORT=$port
TIO2_LOCAL_URL=http://127.0.0.1:$port
TIO2_PUBLIC_URL=https://tio2products.com
TIO2_SITE_SCOPE=tio2-my
TIO2_RELEASE=
TIO2_BOOTSTRAP_MARKER=.runtime/bootstrap-$project
DB_PASSWORD=$(openssl rand -hex 24)
DB_ROOT_PASSWORD=$(openssl rand -hex 24)
D32_ADMIN_PASSWORD=$(openssl rand -hex 24)
EOF
  fi
  load_ci_env
  compose up -d --wait
  if ! compose run --rm wpcli wp core is-installed >/dev/null 2>&1; then
    compose run --rm wpcli wp core install \
      --url="$TIO2_LOCAL_URL" --title='TiO2 Malaysia' --admin_user=d32editor \
      --admin_email=editor@example.test --skip-email --admin_password="$D32_ADMIN_PASSWORD"
  fi
  if [[ ! -f "$MARKER_FILE" ]]; then
    compose run --rm wpcli wp theme activate tio2-malaysia
    compose run --rm wpcli wp eval-file /workspace/scripts/bootstrap.php
    touch "$MARKER_FILE"
  fi
  for _ in $(seq 1 45); do
    curl -fsS "$TIO2_LOCAL_URL/" >/dev/null 2>&1 && return 0
    sleep 2
  done
  echo 'CI WordPress did not become ready' >&2
  return 1
}

run_tests() {
  load_ci_env
  export TEST_BASE_URL="$TIO2_LOCAL_URL"
  export EXPECTED_PUBLIC_URL=https://tio2products.com/
  export EXPECTED_RELEASE=''
  export TEST_OUTPUT_DIR=.runtime/test-results/ci
  export TEST_ENV_FILE="$ENV_FILE"
  export TEST_COMPOSE_FILE=compose.yaml
  export CI=true
  rm -rf "$TEST_OUTPUT_DIR"
  mkdir -p "$TEST_OUTPUT_DIR"
  python tests/portability-test.py
  python tests/runtime-settings-test.py
  node tests/runtime-settings-test.mjs
  docker run --rm --mount "type=bind,source=$ROOT,target=/workspace" -w /workspace \
    wordpress:php8.3-apache@sha256:65919a9ca10940feb10d9400fead0d639bf86241f47c91e2b9ea4703aa8452cf \
    php -d zend.assertions=1 -d assert.exception=1 tests/php/runtime-config-test.php
  compose exec -T wordpress php /workspace/tests/php/content-test.php
  python tests/http-contract.py
  python tests/identity.py
  npx playwright test tests/browser.spec.mjs tests/editor.spec.mjs
  python tests/negative-runtime.py
}

down() {
  if [[ -f "$ENV_FILE" ]]; then
    load_ci_env
    compose down --volumes --remove-orphans
    rm -f "$MARKER_FILE" "$ENV_FILE"
  fi
}

case "${1:-}" in
  up) up ;;
  test) run_tests ;;
  down) down ;;
  *) echo 'usage: ci-environment.sh up|test|down' >&2; exit 64 ;;
esac
