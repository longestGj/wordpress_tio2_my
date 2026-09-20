import re
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[1]
CI_PATH = ROOT / '.github/workflows/ci.yml'
DEPLOY_PATH = ROOT / '.github/workflows/deploy-production.yml'


def load(path):
    document = yaml.safe_load(path.read_text(encoding='utf-8'))
    if True in document and 'on' not in document:
        document['on'] = document.pop(True)
    return document


def uses_values(value):
    if isinstance(value, dict):
        for key, item in value.items():
            if key == 'uses':
                yield item
            yield from uses_values(item)
    elif isinstance(value, list):
        for item in value:
            yield from uses_values(item)


ci = load(CI_PATH)
deploy = load(DEPLOY_PATH)
assert set(ci['on']) == {'workflow_call'}
assert deploy['on']['push']['branches'] == ['main']
assert deploy['jobs']['quality']['uses'] == './.github/workflows/ci.yml'
assert deploy['jobs']['image']['needs'] == 'quality'
assert deploy['jobs']['deploy']['needs'] == ['quality', 'image']
assert deploy['concurrency'] == {'group': 'production', 'cancel-in-progress': False}
assert deploy['permissions'] == {'contents': 'read', 'packages': 'write'}
assert 'environment' not in deploy['jobs']['deploy']

image_steps = deploy['jobs']['image']['steps']
qemu_index = next(
    index for index, step in enumerate(image_steps)
    if str(step.get('uses', '')).startswith('docker/setup-qemu-action@')
)
buildx_index = next(
    index for index, step in enumerate(image_steps)
    if str(step.get('uses', '')).startswith('docker/setup-buildx-action@')
)
image_build = next(
    step for step in image_steps
    if str(step.get('uses', '')).startswith('docker/build-push-action@')
)
assert qemu_index < buildx_index
assert image_build['with']['platforms'] == 'linux/arm64'

expected_names = {
    'PHP and content', 'Browser integration', 'Production deployment contract',
}
assert {job['name'] for job in ci['jobs'].values()} == expected_names

python_setups = [
    step for job in ci['jobs'].values() for step in job.get('steps', [])
    if str(step.get('uses', '')).startswith('actions/setup-python@')
]
assert python_setups
for step in python_setups:
    assert step.get('with', {}).get('cache') == 'pip'
    assert step.get('with', {}).get('cache-dependency-path') == 'requirements-dev.txt'

for workflow in [ci, deploy]:
    for uses in uses_values(workflow):
        if uses == './.github/workflows/ci.yml':
            continue
        assert re.search(r'@[0-9a-f]{40}$', uses), uses

deploy_text = DEPLOY_PATH.read_text(encoding='utf-8')
assert '${{ github.sha }}' in deploy_text
assert 'ghcr.io/longestgj/wordpress_tio2_my:${{ github.sha }}' in deploy_text
assert re.search(r'\bscp\b', deploy_text)
assert re.search(r'\bssh\b', deploy_text)
assert 'StrictHostKeyChecking=no' not in deploy_text
assert ':latest' not in deploy_text
assert 'DB_PASSWORD' not in deploy_text
assert 'WP_ADMIN_PASSWORD' not in deploy_text
assert 'cancel-in-progress: false' in deploy_text

ci_script = (ROOT / 'scripts/ci-environment.sh').read_text(encoding='utf-8')
for required in [
    'TIO2_RFQ_RECEIVER_MODE=fake',
    'tests/php/rfq-model-test.php',
    'tests/php/rfq-submission-test.php',
    'tests/php/rfq-route-test.php',
    'python tests/rfq-http.py',
    'python tests/rfq-endpoint.py',
    'python tests/rfq-isolation.py',
    'python tests/rfq-migration.py',
    'tests/rfq-browser.spec.mjs',
    'tests/rfq-editor.spec.mjs',
]:
    assert required in ci_script, required

print('GitHub workflow trigger, permission, pinning and deployment policy contract passed')
