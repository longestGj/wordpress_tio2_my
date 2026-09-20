#!/usr/bin/env bash
set -Eeuo pipefail

[[ ${EUID:-$(id -u)} -eq 0 ]] || { echo 'Run provision-ubuntu.sh as root' >&2; exit 77; }
[[ $# -eq 1 ]] || { echo 'usage: provision-ubuntu.sh PUBLIC_KEY_FILE' >&2; exit 64; }

PUBLIC_KEY_FILE=$1
[[ -f "$PUBLIC_KEY_FILE" ]] || { echo 'The deploy public-key file does not exist' >&2; exit 66; }

# shellcheck source=/dev/null
source /etc/os-release
[[ "${ID:-}" == ubuntu && "${VERSION_ID:-}" == 24.04 ]] || {
  echo 'Ubuntu 24.04 is required' >&2
  exit 69
}

mapfile -t key_lines <"$PUBLIC_KEY_FILE"
[[ ${#key_lines[@]} -eq 1 && "${key_lines[0]}" == ssh-ed25519\ * ]] || {
  echo 'Provide exactly one ssh-ed25519 public key' >&2
  exit 65
}

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl gnupg openssh-client ufw
ssh-keygen -l -f "$PUBLIC_KEY_FILE" >/dev/null

install -d -m 0755 /etc/apt/keyrings
docker_key_tmp=$(mktemp /etc/apt/keyrings/docker.asc.XXXXXX)
docker_list_tmp=$(mktemp /etc/apt/sources.list.d/docker.list.XXXXXX)
cleanup_repository_files() { rm -f -- "$docker_key_tmp" "$docker_list_tmp"; }
trap cleanup_repository_files EXIT
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o "$docker_key_tmp"
chmod 0644 "$docker_key_tmp"
printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu noble stable\n' \
  "$(dpkg --print-architecture)" >"$docker_list_tmp"
chmod 0644 "$docker_list_tmp"
mv -f -- "$docker_key_tmp" /etc/apt/keyrings/docker.asc
mv -f -- "$docker_list_tmp" /etc/apt/sources.list.d/docker.list
trap - EXIT

apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker

id deploy >/dev/null 2>&1 || useradd --create-home --shell /bin/bash deploy
passwd --lock deploy >/dev/null
usermod --append --groups docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
install -m 600 -o deploy -g deploy "$PUBLIC_KEY_FILE" /home/deploy/.ssh/authorized_keys

install -d -m 750 -o deploy -g deploy \
  /opt/tio2products \
  /opt/tio2products/releases \
  /opt/tio2products/incoming \
  /opt/tio2products/shared \
  /opt/tio2products/shared/state \
  /opt/tio2products/backups \
  /opt/tio2products/logs \
  /opt/tio2products/bin

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
DEPLOY_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
install -m 750 -o deploy -g deploy "$SCRIPT_DIR/receive-release.sh" /opt/tio2products/bin/receive-release.sh
install -m 644 "$DEPLOY_ROOT/systemd/tio2products-backup.service" /etc/systemd/system/tio2products-backup.service
install -m 644 "$DEPLOY_ROOT/systemd/tio2products-backup.timer" /etc/systemd/system/tio2products-backup.timer
systemctl daemon-reload

ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

if [[ -x /opt/tio2products/current/scripts/backup.sh ]]; then
  systemctl enable --now tio2products-backup.timer
else
  systemctl disable --now tio2products-backup.timer >/dev/null 2>&1 || true
  echo 'Backup timer installed; rerun provisioning after the first successful release to enable it.'
fi

echo 'Ubuntu production host provisioning completed.'
