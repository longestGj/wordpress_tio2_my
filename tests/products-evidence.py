import os
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TARGET = ROOT / ".runtime" / "products-evidence-incomplete"
if TARGET.exists():
    shutil.rmtree(TARGET)
TARGET.mkdir(parents=True)
(TARGET / "products-1440.png").write_bytes(b"deliberately incomplete")

result = subprocess.run(
    ["python", "scripts/products-evidence.py", "check-directory", "--evidence-dir", str(TARGET)],
    cwd=ROOT, text=True, encoding="utf-8", stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
    env={**os.environ, "COMPOSE_PROJECT_NAME": "d32-product-000", "TEST_BASE_URL": "http://127.0.0.1:8232"},
)
assert result.returncode != 0
assert "missing required evidence" in result.stdout.lower()
print("Incomplete Products evidence directory is rejected")
