import os
import tempfile
from pathlib import Path

with tempfile.TemporaryDirectory() as temporary:
    os.environ['TEST_BASE_URL'] = 'http://127.0.0.1:8233/'
    os.environ['EXPECTED_PUBLIC_URL'] = 'https://tio2products.com'
    os.environ['EXPECTED_RELEASE'] = '0123456789abcdef0123456789abcdef01234567'
    os.environ['TEST_OUTPUT_DIR'] = temporary
    from support.runtime import runtime_settings

    settings = runtime_settings()
    assert settings['base_url'] == 'http://127.0.0.1:8233'
    assert settings['public_url'] == 'https://tio2products.com/'
    assert settings['release'] == '0123456789abcdef0123456789abcdef01234567'
    assert settings['output_dir'] == str(Path(temporary).resolve())

print('runtime settings contract passed')
