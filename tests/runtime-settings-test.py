import os
import tempfile
from pathlib import Path

with tempfile.TemporaryDirectory() as temporary:
    os.environ['TEST_BASE_URL'] = 'http://127.0.0.1:8233/'
    os.environ['EXPECTED_PUBLIC_URL'] = 'https://tio2products.com'
    os.environ['EXPECTED_RELEASE'] = '0123456789abcdef0123456789abcdef01234567'
    os.environ['TEST_OUTPUT_DIR'] = temporary
    from support.runtime import is_isolated_runtime, runtime_settings, workspace_container_path

    settings = runtime_settings()
    assert settings['base_url'] == 'http://127.0.0.1:8233'
    assert settings['public_url'] == 'https://tio2products.com/'
    assert settings['release'] == '0123456789abcdef0123456789abcdef01234567'
    assert settings['output_dir'] == str(Path(temporary).resolve())
    assert workspace_container_path(Path.cwd() / '.runtime/test-results/content-before.json') == '/workspace/.runtime/test-results/content-before.json'

assert is_isolated_runtime('http://127.0.0.1:8242', 'd32-conv-rfq-gate8', 'd32-conv-rfq-gate8')
assert is_isolated_runtime('http://localhost:49152', 'tio2-ci-12345', 'd32-conv-rfq-gate8')
assert is_isolated_runtime('http://[::1]:49152', 'tio2-ci-run', 'd32-conv-rfq-gate8')
assert not is_isolated_runtime('https://tio2products.com', 'tio2-ci-run', 'd32-conv-rfq-gate8')
assert not is_isolated_runtime('http://127.0.0.1:49152', 'shared-preview', 'd32-conv-rfq-gate8')

print('runtime settings contract passed')
