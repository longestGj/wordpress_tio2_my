import importlib.util
import os
import shutil
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXPECTED_ACCEPTANCE_IDS = {
    "PRODUCT-G6-B02", "PRODUCT-G6-B03", "PRODUCT-G6-TDS-I02", "PRODUCT-G7-B05", "PRODUCT-G7-B06", "PRODUCT-G7-B07",
    "PRODUCT-D32-AC-CONTENT", "PRODUCT-D32-AC-SEO", "PRODUCT-D32-AC-SCHEMA-PREVIEW", "PRODUCT-D32-AC-MIGRATION",
    "PRODUCT-D32-AC-DOMAIN", "PRODUCT-D32-AC-CHROME-REGRESSION",
}
spec = importlib.util.spec_from_file_location("products_evidence", ROOT / "scripts" / "products-evidence.py")
products_evidence = importlib.util.module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(products_evidence)
assert set(products_evidence.ACCEPTANCE_IDS) == EXPECTED_ACCEPTANCE_IDS
mapping = products_evidence.acceptance_mapping("test-commit")
assert {condition["id"] for condition in mapping["conditions"]} == EXPECTED_ACCEPTANCE_IDS
assert all(condition["evidence"] and condition["commands"] and condition["result"] for condition in mapping["conditions"])

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
