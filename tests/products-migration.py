import base64
import hashlib
import json
import os
import subprocess
import urllib.error
import urllib.request
from pathlib import Path
from urllib.parse import urlparse

from support.runtime import runtime_settings

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = runtime_settings()
BASE_URL = RUNTIME["base_url"]
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")
EVIDENCE = Path(os.environ.get("PRODUCT_EVIDENCE_DIR", Path(RUNTIME["output_dir"]) / "products-migration"))

parsed_url = urlparse(BASE_URL)
if parsed_url.scheme != "http" or parsed_url.hostname not in {"127.0.0.1", "localhost", "::1"} or not (PROJECT == "d32-product-000" or PROJECT.startswith("tio2-ci-")):
    raise RuntimeError("Products migration test refuses a non-isolated runtime")


def cli(*args: str) -> str:
    result = subprocess.run(
        ["docker", "compose", "--env-file", RUNTIME["env_file"], "-f", RUNTIME["compose_file"], "run", "--rm", "wpcli", *args],
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
        "version": 2,
        "suspended": False,
        "content_present": True,
        "page_id": original_page_id,
        "page_status": "publish",
        "error": "",
    }
    assert original_page_id > 0
    assert migration("migrate")["page_id"] == original_page_id
    assert migration("migrate")["page_id"] == original_page_id

    cli("post", "meta", "update", str(original_page_id), "_tio2_page_id", "FOREIGN")
    try:
        migration("rollback")
        raise AssertionError("Rollback accepted a page ownership mismatch")
    except subprocess.CalledProcessError:
        pass
    finally:
        cli("post", "meta", "update", str(original_page_id), "_tio2_page_id", "PRODUCT-000")
    assert cli("post", "get", str(original_page_id), "--field=post_status") == "publish"
    assert option("tio2_products_content") == original_products
    assert migration("status")["suspended"] is False

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

    cli("post", "meta", "update", str(original_page_id), "_tio2_page_id", "FOREIGN")
    try:
        migration("resume")
        raise AssertionError("Resume accepted a page ownership mismatch")
    except subprocess.CalledProcessError:
        pass
    finally:
        cli("post", "meta", "update", str(original_page_id), "_tio2_page_id", "PRODUCT-000")
    assert cli("post", "get", str(original_page_id), "--field=post_status") == "trash"
    assert migration("status")["suspended"] is True

    foreign_page_id = cli(
        "post", "create", "--post_type=page", "--post_status=publish",
        "--post_title=Foreign Products", "--post_name=products", "--porcelain",
    )
    try:
        try:
            migration("resume")
            raise AssertionError("Resume accepted an occupied products slug")
        except subprocess.CalledProcessError:
            pass
        assert cli("post", "get", str(original_page_id), "--field=post_status") == "trash"
        assert cli("post", "get", foreign_page_id, "--field=post_name") == "products"
        assert migration("status")["suspended"] is True
    finally:
        cli("post", "delete", foreign_page_id, "--force")

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
EVIDENCE.mkdir(parents=True, exist_ok=True)
(EVIDENCE / "migration-results.json").write_text(json.dumps({
    "pageIdPreserved": final["page_id"] == original_page_id,
    "homepageSha256": home_hash,
    "productsRestored": option("tio2_products_content") == original_products,
    "idempotent": True,
    "cmsEditPreservedBeforeRollback": True,
    "rollbackStatus": 404,
    "resumeStatus": 200,
}, indent=2) + "\n", encoding="utf-8")
print("Products migration is idempotent, edit-preserving, reversible, resumable, and isolated")
