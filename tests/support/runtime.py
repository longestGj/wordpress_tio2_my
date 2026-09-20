import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def runtime_settings() -> dict[str, str]:
    output = Path(os.getenv('TEST_OUTPUT_DIR', ROOT / '.runtime/test-results')).resolve()
    output.mkdir(parents=True, exist_ok=True)
    return {
        'base_url': os.getenv('TEST_BASE_URL', 'http://127.0.0.1:8232').rstrip('/'),
        'public_url': os.getenv('EXPECTED_PUBLIC_URL', 'https://tio2products.com').rstrip('/') + '/',
        'release': os.getenv('EXPECTED_RELEASE', ''),
        'output_dir': str(output),
        'env_file': os.getenv('TEST_ENV_FILE', '.env'),
        'compose_file': os.getenv('TEST_COMPOSE_FILE', 'compose.yaml'),
    }
