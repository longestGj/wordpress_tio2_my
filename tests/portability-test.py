from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FILES = [
    'tests/http-contract.py',
    'tests/identity.py',
    'tests/browser.spec.mjs',
    'tests/editor.spec.mjs',
    'tests/negative-runtime.py',
    'playwright.config.js',
]

for relative in FILES:
    text = (ROOT / relative).read_text(encoding='utf-8')
    assert 'docs/verification/home' not in text, relative
    assert "'http://127.0.0.1:8232'" not in text, relative
    assert '"http://127.0.0.1:8232"' not in text, relative

print('test portability guard passed')
