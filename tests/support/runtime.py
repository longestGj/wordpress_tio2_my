import os
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[2]


def is_isolated_runtime(base_url: str, project: str, dedicated_project: str) -> bool:
    parsed = urlsplit(base_url)
    return (
        parsed.scheme == 'http'
        and parsed.hostname in {'127.0.0.1', 'localhost', '::1'}
        and (project == dedicated_project or project.startswith('tio2-ci-'))
    )


def workspace_container_path(path: str | Path) -> str:
    relative = Path(path).resolve().relative_to(ROOT)
    return '/workspace/' + relative.as_posix()


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
