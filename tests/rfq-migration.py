import base64
import hashlib
import json
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("TEST_BASE_URL", "").rstrip("/")
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")
EVIDENCE = Path(os.environ.get("RFQ_EVIDENCE_DIR", ".runtime/request-a-quote-migration"))

if BASE_URL != "http://127.0.0.1:8242" or PROJECT != "d32-conv-rfq-gate8":
    raise RuntimeError("RFQ migration test refuses a non-isolated runtime")


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
    return json.loads(cli("eval-file", "/workspace/scripts/rfq-migration.php", action))


def option(name: str) -> dict:
    return json.loads(cli("option", "get", name, "--format=json"))


def write_option(name: str, value: dict) -> None:
    payload = base64.b64encode(json.dumps(value, ensure_ascii=False).encode()).decode()
    cli("eval", f"update_option('{name}',json_decode(base64_decode('{payload}'),true),false);")


def sha(value: dict) -> str:
    encoded = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(encoded).hexdigest()


initial = migration("status")
original_rfq = option("tio2_rfq_content")
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

    changed = json.loads(json.dumps(original_rfq))
    changed["fields"]["hero.body"] = "Temporary RFQ migration preservation check."
    write_option("tio2_rfq_content", changed)
    migration("migrate")
    assert option("tio2_rfq_content")["fields"]["hero.body"] == "Temporary RFQ migration preservation check."
    assert sha(option("tio2_content")) == home_hash

    rolled_back = migration("rollback")
    assert rolled_back["suspended"] is True
    assert rolled_back["content_present"] is False
    assert rolled_back["page_id"] == 0 and rolled_back["page_status"] is None
    assert sha(option("tio2_content")) == home_hash

    resumed = migration("resume")
    assert resumed["page_id"] == original_page_id
    assert resumed["page_status"] == "publish"
    assert sha(option("tio2_content")) == home_hash
finally:
    current = migration("status")
    if current["suspended"] or current["page_status"] != "publish":
        migration("resume")
    write_option("tio2_rfq_content", original_rfq)

final = migration("status")
assert final["page_id"] == original_page_id and final["page_status"] == "publish"
assert option("tio2_rfq_content") == original_rfq
assert sha(option("tio2_content")) == home_hash
EVIDENCE.mkdir(parents=True, exist_ok=True)
(EVIDENCE / "migration-results.json").write_text(json.dumps({
    "pageIdPreserved": final["page_id"] == original_page_id,
    "homepageSha256": home_hash,
    "rfqRestored": option("tio2_rfq_content") == original_rfq,
    "idempotent": True,
    "cmsEditPreservedBeforeRollback": True,
    "rollbackPageStatus": None,
    "resumePageStatus": "publish",
}, indent=2) + "\n", encoding="utf-8")
print("RFQ migration is idempotent, edit-preserving, reversible, resumable, and isolated")
