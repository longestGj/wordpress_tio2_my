import json
import copy
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / '.runtime'
RUNTIME.mkdir(exist_ok=True)
ENV_FILE = RUNTIME / 'production-config.env'
SHA = '0123456789abcdef0123456789abcdef01234567'
SECRET_VALUES = [f'{name.lower()}-8f4d2c1a6b7e9d0c' for name in [
    'db_password', 'db_root_password', 'admin_password', 'auth_key', 'secure_auth_key',
    'logged_in_key', 'nonce_key', 'auth_salt', 'secure_auth_salt', 'logged_in_salt', 'nonce_salt',
]]

values = {
    'APP_IMAGE': f'ghcr.io/longestgj/wordpress_tio2_my:{SHA}',
    'DB_NAME': 'wordpress',
    'DB_USER': 'wordpress',
    'DB_PASSWORD': SECRET_VALUES[0],
    'DB_ROOT_PASSWORD': SECRET_VALUES[1],
    'WP_ADMIN_USER': 'production-admin',
    'WP_ADMIN_PASSWORD': SECRET_VALUES[2],
    'WP_ADMIN_EMAIL': 'admin@example.test',
    'WORDPRESS_AUTH_KEY': SECRET_VALUES[3],
    'WORDPRESS_SECURE_AUTH_KEY': SECRET_VALUES[4],
    'WORDPRESS_LOGGED_IN_KEY': SECRET_VALUES[5],
    'WORDPRESS_NONCE_KEY': SECRET_VALUES[6],
    'WORDPRESS_AUTH_SALT': SECRET_VALUES[7],
    'WORDPRESS_SECURE_AUTH_SALT': SECRET_VALUES[8],
    'WORDPRESS_LOGGED_IN_SALT': SECRET_VALUES[9],
    'WORDPRESS_NONCE_SALT': SECRET_VALUES[10],
    'PUBLIC_URL': 'https://tio2products.com',
    'SITE_SCOPE': 'tio2-my',
    'RELEASE_SHA': SHA,
    'TIO2_CONTENT_INIT_VERSION': 'home-v1',
    'ACME_EMAIL': 'admin@example.test',
    'COMPOSE_PROJECT_NAME': 'tio2products-config-test',
    'VOLUME_PREFIX': 'tio2products_config_test',
}
ENV_FILE.write_text(''.join(f'{key}={value}\n' for key, value in values.items()), encoding='utf-8')

rendered = subprocess.check_output([
    'docker', 'compose', '--env-file', str(ENV_FILE),
    '-f', str(ROOT / 'deploy/compose.production.yaml'), 'config', '--format', 'json',
], cwd=ROOT, text=True, encoding='utf-8')
config = json.loads(rendered)

assert set(config['services']) == {'db', 'wordpress', 'caddy'}
assert 'ports' not in config['services']['db']
assert 'ports' not in config['services']['wordpress']
ports = [
    {key: str(port[key]) if key == 'published' else port[key] for key in ['mode', 'target', 'published', 'protocol']}
    for port in config['services']['caddy']['ports']
]
assert ports == [
    {'mode': 'ingress', 'target': 80, 'published': '80', 'protocol': 'tcp'},
    {'mode': 'ingress', 'target': 443, 'published': '443', 'protocol': 'tcp'},
    {'mode': 'ingress', 'target': 443, 'published': '443', 'protocol': 'udp'},
]
assert config['services']['wordpress']['image'].endswith(':' + SHA)
assert all(service['restart'] == 'unless-stopped' for service in config['services'].values())
assert set(config['services']['db']['networks']) == {'backend'}
assert set(config['services']['wordpress']['networks']) == {'backend', 'edge'}
assert set(config['services']['caddy']['networks']) == {'edge'}
assert all(service['logging']['options'] == {'max-file': '3', 'max-size': '10m'} for service in config['services'].values())
assert [mount['target'] for mount in config['services']['wordpress']['volumes']] == ['/var/www/html/wp-content/uploads']

assert not any(key.startswith('WP_ADMIN_') for key in config['services']['wordpress']['environment'])
redacted = copy.deepcopy(config)
allowed_secret_keys = {
    'db': {'MARIADB_PASSWORD', 'MARIADB_ROOT_PASSWORD'},
    'wordpress': {
        'WORDPRESS_DB_PASSWORD', 'WORDPRESS_AUTH_KEY', 'WORDPRESS_SECURE_AUTH_KEY',
        'WORDPRESS_LOGGED_IN_KEY', 'WORDPRESS_NONCE_KEY', 'WORDPRESS_AUTH_SALT',
        'WORDPRESS_SECURE_AUTH_SALT', 'WORDPRESS_LOGGED_IN_SALT', 'WORDPRESS_NONCE_SALT',
    },
}
for service, keys in allowed_secret_keys.items():
    for key in keys:
        redacted['services'][service]['environment'][key] = '<redacted>'
redacted_rendered = json.dumps(redacted, sort_keys=True)
for secret in SECRET_VALUES:
    assert secret not in redacted_rendered

caddyfile = ROOT / 'deploy/Caddyfile'
subprocess.run([
    'docker', 'run', '--rm', '--mount', f'type=bind,source={caddyfile},target=/etc/caddy/Caddyfile,readonly',
    '-e', 'ACME_EMAIL=admin@example.test',
    'caddy:2.10.2-alpine@sha256:4c6e91c6ed0e2fa03efd5b44747b625fec79bc9cd06ac5235a779726618e530d',
    'caddy', 'validate', '--config', '/etc/caddy/Caddyfile', '--adapter', 'caddyfile',
], check=True, cwd=ROOT)

print('production Compose, Caddy, network and secret-boundary contract passed')
