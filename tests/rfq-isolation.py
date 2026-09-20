import hashlib
import json
import os
import subprocess
import urllib.error
import urllib.request
from pathlib import Path

from support.runtime import is_isolated_runtime, runtime_settings, workspace_container_path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME = runtime_settings()
if not is_isolated_runtime(RUNTIME["base_url"], os.environ.get("COMPOSE_PROJECT_NAME", ""), "d32-conv-rfq-gate8"):
    raise RuntimeError("RFQ isolation test refuses a non-isolated runtime")
OUT = Path(RUNTIME["output_dir"])
OUT.mkdir(parents=True, exist_ok=True)


def cli(*args: str) -> str:
    return subprocess.run(
        ["docker", "compose", "--env-file", RUNTIME["env_file"], "-f", RUNTIME["compose_file"], "run", "--rm", "wpcli", *args],
        cwd=ROOT, capture_output=True, text=True, encoding="utf-8", check=True,
    ).stdout.strip()


def request(path: str):
    try:
        with urllib.request.urlopen(RUNTIME["base_url"] + path) as response:
            return response.status, response.read().decode()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode()


original = json.loads(cli("eval-file", "/workspace/scripts/snapshot.php", "export"))
home_hash = hashlib.sha256(json.dumps(original["content"], ensure_ascii=False, sort_keys=True).encode()).hexdigest()
restore_path = OUT / "rfq-isolation-restore.json"
restore_path.write_text(json.dumps(original, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
page_id = int(cli("post", "list", "--post_type=page", "--name=request-a-quote", "--field=ID"))
original_page_scope = cli("post", "meta", "get", str(page_id), "_tio2_site_scope")
results = []

for mode in ["wrong-scope", "missing-content", "missing-page-scope"]:
    try:
        if mode == "wrong-scope":
            cli("eval", "$d=get_option('tio2_rfq_content');$d['site_scope']='tio2-foreign';update_option('tio2_rfq_content',$d,false);")
            expected = 503
        elif mode == "missing-content":
            cli("option", "update", "tio2_rfq_migration_suspended", "1")
            cli("option", "delete", "tio2_rfq_content")
            expected = 503
        else:
            cli("post", "meta", "delete", str(page_id), "_tio2_site_scope")
            expected = 503
        observations = [request("/request-a-quote/") for _ in range(2)]
        assert [status for status, _ in observations] == [expected, expected], (mode, observations)
        for _, body in observations:
            assert "Request a Titanium Dioxide Quote" not in body
            assert "tio2-foreign" not in body
        home_status, home_body = request("/")
        assert home_status == 200 and "Malaysia Titanium Dioxide for Industrial Buyers" in home_body
        current_home = json.loads(cli("option", "get", "tio2_content", "--format=json"))
        current_home_hash = hashlib.sha256(json.dumps(current_home, ensure_ascii=False, sort_keys=True).encode()).hexdigest()
        assert current_home_hash == home_hash
        results.append({"mode": mode, "statuses": [expected, expected], "warmColdConsistent": True, "homeUnchanged": True})
    finally:
        cli("eval-file", "/workspace/scripts/snapshot.php", "restore", workspace_container_path(restore_path))
        cli("post", "meta", "update", str(page_id), "_tio2_site_scope", original_page_scope)
        cli("option", "delete", "tio2_rfq_migration_suspended")
        cli("eval-file", "/workspace/scripts/rfq-migration.php", "migrate")
    assert request("/request-a-quote/")[0] == 200

restored = json.loads(cli("eval-file", "/workspace/scripts/snapshot.php", "export"))
assert restored["content"] == original["content"] and restored["rfq_content"] == original["rfq_content"]
(OUT / "rfq-isolation.json").write_text(json.dumps({"cases": results, "restoredExactly": True}, indent=2) + "\n", encoding="utf-8")
print("RFQ wrong/missing content scope and page-scope warm/cold isolation scenarios passed")
