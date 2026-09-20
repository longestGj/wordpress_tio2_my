import base64
import hashlib
import json
import os
import subprocess
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("TEST_BASE_URL", "http://127.0.0.1:8232").rstrip("/")
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")

if BASE_URL != "http://127.0.0.1:8232" or PROJECT != "d32-product-000":
    raise RuntimeError("Products migration test refuses a non-isolated runtime")


def cli(*args: str) -> str:
    result = subprocess.run(
        ["docker", "compose", "run", "--rm", "wpcli", *args],
        cwd=ROOT,
        text=True,
        encoding="utf-8",
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=True,
    )
    return result.stdout.strip()


def migration(action: str) -> dict:
    return json.loads(cli("eval-file", "/workspace/scripts/products-migration.php", action))


def option(name: str) -> dict:
    return json.loads(cli("option", "get", name, "--format=json"))


def sha(value: dict) -> str:
    encoded = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(encoded).hexdigest()


def status_code(path: str) -> int:
    try:
        return urllib.request.urlopen(BASE_URL + path).status
    except urllib.error.HTTPError as error:
        return error.code


initial = migration("status")
original_products = option("tio2_products_content")
home_hash = sha(option("tio2_content"))
original_page_id = initial["page_id"]

try:
    assert initial == {
        "version": 1,
        "suspended": False,
        "content_present": True,
        "page_id": original_page_id,
        "page_status": "publish",
        "error": "",
    }
    assert original_page_id > 0
    assert migration("migrate")["page_id"] == original_page_id
    assert migration("migrate")["page_id"] == original_page_id

    changed = json.loads(json.dumps(original_products))
    changed["fields"]["directory.grade.1.summary"] = "Temporary migration preservation check."
    payload = base64.b64encode(json.dumps(changed, ensure_ascii=False).encode()).decode()
    cli("eval", f"update_option('tio2_products_content',json_decode(base64_decode('{payload}'),true),false);")
    migration("migrate")
    assert option("tio2_products_content")["fields"]["directory.grade.1.summary"] == "Temporary migration preservation check."
    assert sha(option("tio2_content")) == home_hash

    rolled_back = migration("rollback")
    assert rolled_back["suspended"] is True
    assert rolled_back["content_present"] is False
    assert status_code("/products/") == 404
    assert status_code("/") == 200
    assert sha(option("tio2_content")) == home_hash

    resumed = migration("resume")
    assert resumed["page_id"] == original_page_id
    assert resumed["page_status"] == "publish"
    assert status_code("/products/") == 200
    restored = option("tio2_products_content")
    assert restored["fields"]["directory.grade.1.summary"] == original_products["fields"]["directory.grade.1.summary"]
    assert sha(option("tio2_content")) == home_hash
finally:
    current = migration("status")
    if current["suspended"] or current["page_status"] != "publish":
        migration("resume")
    payload = base64.b64encode(json.dumps(original_products, ensure_ascii=False).encode()).decode()
    cli("eval", f"update_option('tio2_products_content',json_decode(base64_decode('{payload}'),true),false);")

final = migration("status")
assert final["page_id"] == original_page_id and final["page_status"] == "publish"
assert option("tio2_products_content") == original_products
assert sha(option("tio2_content")) == home_hash
print("Products migration is idempotent, edit-preserving, reversible, resumable, and isolated")
