import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PROVISION = ROOT / 'deploy/scripts/provision-ubuntu.sh'
CONFIGURE = ROOT / 'deploy/scripts/configure-env.sh'
RECEIVER = ROOT / 'deploy/scripts/receive-release.sh'
DEPLOY = ROOT / 'deploy/scripts/deploy.sh'
ROLLBACK = ROOT / 'deploy/scripts/rollback.sh'
SERVICE = ROOT / 'deploy/systemd/tio2products-backup.service'
TIMER = ROOT / 'deploy/systemd/tio2products-backup.timer'

for path in (PROVISION, CONFIGURE, RECEIVER, DEPLOY, ROLLBACK, SERVICE, TIMER):
    assert path.is_file(), f'missing provisioning artifact: {path.relative_to(ROOT)}'

provision = PROVISION.read_text(encoding='utf-8')
configure = CONFIGURE.read_text(encoding='utf-8')
receiver = RECEIVER.read_text(encoding='utf-8')
deploy = DEPLOY.read_text(encoding='utf-8')
rollback = ROLLBACK.read_text(encoding='utf-8')
service = SERVICE.read_text(encoding='utf-8')
timer = TIMER.read_text(encoding='utf-8')

assert re.search(r'EUID.*(?:-ne 0|-eq 0)', provision)
assert '/etc/os-release' in provision and 'VERSION_ID' in provision and '24.04' in provision
assert re.search(r'-f\s+"?\$PUBLIC_KEY_FILE', provision)
assert 'ssh-ed25519' in provision and 'ssh-keygen -l' in provision
assert 'https://download.docker.com/linux/ubuntu' in provision
for package in ('docker-ce', 'docker-ce-cli', 'containerd.io', 'docker-buildx-plugin', 'docker-compose-plugin'):
    assert package in provision
assert 'systemctl enable --now docker' in provision
assert 'useradd --create-home --shell /bin/bash deploy' in provision
assert 'passwd --lock deploy' in provision
assert 'usermod --append --groups docker deploy' in provision
assert '/home/deploy/.ssh' in provision and 'authorized_keys' in provision
for directory in ('releases', 'incoming', 'shared/state', 'backups', 'logs', 'bin'):
    assert f'/opt/tio2products/{directory}' in provision
assert provision.index('ufw allow OpenSSH') < provision.index('ufw --force enable')
assert 'ufw allow 80/tcp' in provision and 'ufw allow 443/tcp' in provision
assert 'clear_legacy_oci_rejects' in provision
assert 'icmp-host-prohibited' in provision and 'icmp6-adm-prohibited' in provision
assert re.search(r'clear_legacy_oci_rejects\s*\nufw --force enable', provision)
assert 'PermitRootLogin' not in provision
assert 'receive-release.sh' in provision
assert 'tio2products-backup.service' in provision and 'tio2products-backup.timer' in provision
assert re.search(r'-x\s+/opt/tio2products/current/scripts/backup\.sh', provision)

assert re.search(r'EUID.*(?:-ne 0|-eq 0)', configure)
assert 'umask 077' in configure
assert len(re.findall(r'read\s+[^\n]*-s', configure)) >= 3
assert 'openssl rand -hex 32' in configure
for name in (
    'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'WORDPRESS_AUTH_KEY', 'WORDPRESS_SECURE_AUTH_KEY',
    'WORDPRESS_LOGGED_IN_KEY', 'WORDPRESS_NONCE_KEY', 'WORDPRESS_AUTH_SALT',
    'WORDPRESS_SECURE_AUTH_SALT', 'WORDPRESS_LOGGED_IN_SALT', 'WORDPRESS_NONCE_SALT',
    'WP_ADMIN_USER', 'WP_ADMIN_PASSWORD', 'WP_ADMIN_EMAIL', 'ACME_EMAIL',
):
    assert name in configure
assert 'mktemp' in configure and re.search(r'\bmv\b', configure)
assert re.search(r'chown\s+deploy:deploy', configure)
assert re.search(r'chmod\s+600', configure)
for secret in ('DB_PASSWORD', 'DB_ROOT_PASSWORD', 'WORDPRESS_AUTH_KEY', 'WP_ADMIN_PASSWORD'):
    assert not re.search(rf'(echo|printf)[^\n]*\${{{secret}}}|(echo|printf)[^\n]*\${secret}\b', configure)

assert 'validate_release_sha' in receiver
assert 'realpath -e' in receiver and '/opt/tio2products' in receiver and 'incoming/' in receiver
assert '-tzf' in receiver and '-tvzf' in receiver
assert '--no-same-owner' in receiver and '--no-same-permissions' in receiver
assert re.search(r'\(\^\|/\).*\\\.\\\.', receiver)
assert 'scripts/deploy.sh' in receiver
assert receiver.index('scripts/deploy.sh') < receiver.rindex('rm -f')
assert 'bash "$SCRIPT_DIR/bootstrap.sh" "$dir"' in deploy
assert 'bash "$SCRIPT_DIR/healthcheck.sh" "$dir"' in deploy
assert 'bash "$old_dir/scripts/healthcheck.sh" "$old_dir"' in deploy
assert 'TIO2_LOCK_HELD=1 bash "$SCRIPT_DIR/backup.sh" --release "$old_release"' in deploy
assert 'bash "$target_dir/scripts/bootstrap.sh" "$target_dir"' in rollback
assert 'bash "$target_dir/scripts/healthcheck.sh" "$target_dir"' in rollback
assert 'bash "$old_dir/scripts/healthcheck.sh" "$old_dir"' in rollback
assert 'TIO2_LOCK_HELD=1 bash "$SCRIPT_DIR/backup.sh" --release "$old_release"' in rollback

assert 'Requires=docker.service' in service
assert 'After=docker.service' in service
assert 'Type=oneshot' in service
assert 'User=deploy' in service and 'Group=deploy' in service
assert 'ExecStart=/opt/tio2products/current/scripts/backup.sh --scheduled' in service
assert 'OnCalendar=*-*-* 02:20:00 UTC' in timer
assert 'Persistent=true' in timer
assert 'RandomizedDelaySec=10m' in timer
assert 'WantedBy=timers.target' in timer

print('Ubuntu provisioning, secret configuration, release receiver and backup unit contract passed')
