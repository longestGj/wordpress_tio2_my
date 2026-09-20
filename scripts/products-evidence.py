#!/usr/bin/env python3
"""Capture and bind PRODUCT-000 Gate 8 evidence without touching HOME-001 evidence."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import shutil
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_EVIDENCE = ROOT / "docs/verification/products"
DEFAULT_RECEIPT = ROOT / "docs/handoffs/PRODUCT-000-gate8.md"
DEFAULT_MANIFEST = ROOT / ".runtime/handoff/products/gate8_evidence_manifest.json"
BASELINE_COMMIT = "f520678e723fe040c2e3b2cb5f9bd219e33d8fb9"
NPX = "npx.cmd" if os.name == "nt" else "npx"
ACCEPTANCE_IDS = ["PRODUCT-G6-B02", "PRODUCT-G6-B03", "PRODUCT-G6-TDS-I02", "PRODUCT-G7-B05", "PRODUCT-G7-B06", "PRODUCT-G7-B07"]
REQUIRED_EVIDENCE = {
    "acceptance.md", "acceptance-mapping.json", "artifact.json", "dependencies.json", "editor-changed.png",
    "editor-results.json", "environment.json", "faq-keyboard-768.png", "home-content.json", "home-response.html",
    "identity.json", "layout-1024.json", "layout-1440.json", "layout-390.json", "layout-768.json",
    "menu-390.png", "cookie-390.png", "migration-results.json", "products-1024.png", "products-1440.png",
    "products-390.png", "products-768.png", "products-content-before.json", "products-content-restored.json",
    "products-content.json", "products-response.html", "readiness-matrix.json", "selector-not-sure-1024.png",
    "seo-schema.json", "source-hashes.json", "test-results.json",
}


def run(args: list[str], *, check: bool = True, env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, cwd=ROOT, text=True, encoding="utf-8", errors="replace", stdout=subprocess.PIPE, stderr=subprocess.STDOUT, check=check, env=env)


def quiet(args: list[str]) -> str:
    return subprocess.run(args, cwd=ROOT, text=True, encoding="utf-8", errors="replace", stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True).stdout.strip()


def git(*args: str) -> str:
    return run(["git", *args]).stdout.strip()


def sha(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def relative(path: Path) -> str:
    return path.resolve().relative_to(ROOT.resolve()).as_posix()


def option(name: str) -> dict:
    output = quiet(["docker", "compose", "run", "--rm", "wpcli", "option", "get", name, "--format=json"])
    return json.loads(output)


def compact_hash(value: dict) -> str:
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, separators=(",", ":")).encode()).hexdigest()


def page(path: str) -> tuple[int, bytes, dict[str, str]]:
    request = urllib.request.Request("http://127.0.0.1:8232" + path, headers={"User-Agent":"PRODUCT-000-Gate8-Evidence/1.0"})
    try:
        response = urllib.request.build_opener(urllib.request.ProxyHandler({})).open(request, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read(), dict(response.headers.items())


def assert_runtime() -> None:
    if os.environ.get("COMPOSE_PROJECT_NAME") != "d32-product-000" or os.environ.get("TEST_BASE_URL") != "http://127.0.0.1:8232":
        raise RuntimeError("Evidence tooling refuses a non-isolated runtime")


def check_clean(*, allow_evidence: bool = False, evidence: Path = DEFAULT_EVIDENCE) -> None:
    lines = [line for line in git("status", "--porcelain").splitlines() if line]
    if allow_evidence:
        prefix = relative(evidence).rstrip("/") + "/"
        lines = [line for line in lines if not line[3:].replace("\\", "/").startswith(prefix)]
    if lines:
        raise RuntimeError("Dirty implementation tree: " + "; ".join(lines))


def check_directory(evidence: Path) -> None:
    present = {path.name for path in evidence.iterdir() if path.is_file()} if evidence.is_dir() else set()
    missing = sorted(REQUIRED_EVIDENCE - present)
    if missing:
        raise RuntimeError("Missing required evidence: " + ", ".join(missing))


def suite(evidence: Path) -> None:
    assert_runtime()
    target = evidence.resolve()
    allowed = DEFAULT_EVIDENCE.resolve()
    if target != allowed:
        raise RuntimeError(f"Final suite evidence directory must be {allowed}")
    # A failed evidence run may leave only generated evidence behind. Permit
    # that exact directory so the next run can replace it atomically while
    # still refusing every implementation-tree change.
    check_clean(allow_evidence=True, evidence=evidence)
    if target.exists():
        shutil.rmtree(target)
    target.mkdir(parents=True)
    home_output = ROOT / ".runtime/home-regression"
    if home_output.exists():
        shutil.rmtree(home_output)
    home_output.mkdir(parents=True)
    env = {**os.environ, "COMPOSE_PROJECT_NAME":"d32-product-000", "TEST_BASE_URL":"http://127.0.0.1:8232", "PRODUCT_EVIDENCE_DIR":relative(evidence), "HOME_EVIDENCE_DIR":relative(home_output)}
    commands: list[tuple[str, list[str]]] = []
    for path in sorted((ROOT / "wp-content/plugins/tio2-content").rglob("*.php")) + sorted((ROOT / "wp-content/themes/tio2-malaysia").rglob("*.php")):
        commands.append(("php-lint:" + relative(path), ["docker","compose","exec","-T","wordpress","php","-l","/workspace/" + relative(path)]))
    commands.extend([
        ("home-content-contract", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/content-test.php"]),
        ("runtime-config-contract", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/runtime-config-test.php"]),
        ("products-model-contract", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/products-model-test.php"]),
        ("products-route-contract", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/products-route-test.php"]),
        ("home-http", [sys.executable,"tests/http-contract.py"]),
        ("products-http", [sys.executable,"tests/products-http.py"]),
        ("products-browser", [NPX,"playwright","test","tests/products-browser.spec.mjs","--reporter=line"]),
        ("products-editor", [NPX,"playwright","test","tests/products-editor.spec.mjs","--reporter=line"]),
        ("products-readiness", [sys.executable,"tests/products-readiness.py"]),
        ("products-migration", [sys.executable,"tests/products-migration.py"]),
        ("home-browser-regression", [NPX,"playwright","test","tests/browser.spec.mjs","--reporter=line"]),
        ("home-editor-regression", [NPX,"playwright","test","tests/editor.spec.mjs","--reporter=line"]),
        ("runtime-identity", [sys.executable,"tests/identity.py"]),
        ("negative-runtime", [sys.executable,"tests/negative-runtime.py"]),
    ])
    results = []
    failed = False
    for name, command in commands:
        started = time.monotonic()
        completed = run(command, check=False, env=env)
        record = {"name":name, "command":command, "returncode":completed.returncode, "durationSeconds":round(time.monotonic()-started, 3), "output":completed.stdout.strip()}
        results.append(record)
        print(f"[{name}] exit={completed.returncode}")
        if completed.stdout.strip(): print(completed.stdout.strip())
        if completed.returncode:
            failed = True
            break
    (evidence / "test-results.json").write_text(json.dumps({"implementationCommit":git("rev-parse","HEAD"), "capturedAt":utc_now(), "passed":not failed, "commands":results}, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if failed:
        raise RuntimeError("Complete suite failed; see test-results.json")


def artifact_entries() -> list[tuple[str, str]]:
    entries = []
    for folder in ["wp-content/plugins/tio2-content", "wp-content/themes/tio2-malaysia"]:
        for path in (ROOT / folder).rglob("*"):
            if path.is_file(): entries.append((relative(path), sha(path)))
    return sorted(entries)


def capture(evidence: Path) -> None:
    assert_runtime()
    check_clean(allow_evidence=True, evidence=evidence)
    implementation = git("rev-parse", "HEAD")
    entries = artifact_entries()
    for path, _digest in entries:
        committed = run(["git","rev-parse",f"{implementation}:{path}"]).stdout.strip()
        current = run(["git","hash-object",path]).stdout.strip()
        if committed != current: raise RuntimeError("Uncommitted custom code: " + path)
    build_id = "wp-" + hashlib.sha256("".join(f"{path}\0{digest}\n" for path,digest in entries).encode()).hexdigest()
    build_dir = ROOT / ".runtime/artifacts" / build_id
    for path, digest in entries:
        destination = build_dir / path
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / path, destination)
        if sha(destination) != digest: raise RuntimeError("Artifact copy mismatch: " + path)
    (build_dir / "BUILD_ID").write_text(build_id + "\n", encoding="utf-8")
    artifact = {"build_id":build_id, "implementation_commit":implementation, "directory":relative(build_dir), "files":dict(entries)}
    (build_dir / "artifact.json").write_text(json.dumps(artifact, indent=2) + "\n", encoding="utf-8")
    (evidence / "artifact.json").write_text(json.dumps(artifact, indent=2) + "\n", encoding="utf-8")

    home_content, products_content = option("tio2_content"), option("tio2_products_content")
    (evidence / "home-content.json").write_text(json.dumps(home_content, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (evidence / "products-content.json").write_text(json.dumps(products_content, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    home_status, home_html, home_headers = page("/")
    products_status, products_html, product_headers = page("/products/")
    (evidence / "home-response.html").write_bytes(home_html)
    home_soup, products_soup = BeautifulSoup(home_html,"html.parser"), BeautifulSoup(products_html,"html.parser")
    home_hash, products_hash = compact_hash(home_content), compact_hash(products_content)
    identity = {
        "buildId":build_id, "homeContentSha256":home_hash, "productsContentSha256":products_hash,
        "homeStatus":home_status, "productsStatus":products_status,
        "homeArtifactMarker":home_soup.select_one('meta[name="tio2-artifact"]')["content"],
        "productsArtifactMarker":products_soup.select_one('meta[name="tio2-artifact"]')["content"],
        "homeContentMarker":home_soup.select_one('meta[name="tio2-content-sha256"]')["content"],
        "productsContentMarker":products_soup.select_one('meta[name="tio2-products-content-sha256"]')["content"],
        "homeScopeHeader":home_headers.get("X-Site-Scope"), "productsScopeHeader":product_headers.get("X-Site-Scope"),
    }
    if not (identity["homeArtifactMarker"] == identity["productsArtifactMarker"] == build_id and identity["homeContentMarker"] == home_hash and identity["productsContentMarker"] == products_hash):
        raise RuntimeError("Runtime identity markers do not match source/content")
    (evidence / "identity.json").write_text(json.dumps(identity, indent=2) + "\n", encoding="utf-8")

    sources = [
        ROOT / "docs/superpowers/plans/2026-09-20-product-000-products.md",
        Path("D:/23MySec/pages/products/PRODUCT-000_D32_CURRENT_GATE_BASELINE_MANIFEST_V0.1.md"),
        Path("D:/23MySec/pages/products/05_review/PRODUCT-000_D32_GATE6_CONTROLLER_CLOSURE_V0.1.md"),
        Path("D:/23MySec/pages/products/06_handoff/PRODUCT-000_D32_GATE6_HANDOFF_PACKAGE_V0.1.md"),
        Path("D:/23MySec/pages/products/05_review/PRODUCT-000_D32_GATE4_GATE5_HANDOFF_V0.1.md"),
        Path("D:/23MySec/pages/products/04_planning/d32-gate4-v0.1/product-visual.html"),
        Path("D:/23MySec/pages/products/06_handoff/PRODUCT-000_GATE7_ACCEPTANCE_AND_BLOCKERS_V0.3.md"),
    ]
    source_hashes = [{"path":path.as_posix(), "sha256":sha(path)} for path in sources]
    (evidence / "source-hashes.json").write_text(json.dumps(source_hashes, indent=2) + "\n", encoding="utf-8")

    paths = {
        "CONV-RFQ":"/request-a-quote/", "PRODUCT-PROC-CL":"/products/chloride-process-titanium-dioxide/", "PRODUCT-PROC-SU":"/products/sulfate-process-titanium-dioxide/",
        "APP-000":"/applications/", "DOC-000":"/documents/", "MARKET-000":"/markets/",
    }
    grade_ids = ["M350","M510","M896","M996","M2196","M895","M200","M108","M210","M340","M886","M52","M2377","CR901"]
    for index, grade_id in enumerate(grade_ids, 1): paths["GRADE-"+grade_id] = products_content["fields"][f"directory.grade.{index}.path"]
    dependencies = []
    for key, path in paths.items():
        status, _body, _headers = page(path)
        dependencies.append({"routeKey":key,"path":path,"status":status,"siteScope":"tio2-my","ready":False,"owner":"External target-page owner","interpretation":"Not implemented by PRODUCT-000; Hub fails closed except fixed RFQ visibility"})
    if any(item["status"] != 404 for item in dependencies): raise RuntimeError("Initial external dependency state is not the approved all-unready state")
    (evidence / "dependencies.json").write_text(json.dumps(dependencies, indent=2) + "\n", encoding="utf-8")

    environment = {
        "capturedAt":utc_now(), "composeProject":"d32-product-000", "runtime":"http://127.0.0.1:8232", "siteScope":"tio2-my",
        "wordpress":quiet(["docker","compose","run","--rm","wpcli","core","version"]),
        "php":quiet(["docker","compose","exec","-T","wordpress","php","-r","echo PHP_VERSION;"]),
        "dockerClient":quiet(["docker","version","--format","{{.Client.Version}}"]),
        "branch":git("branch","--show-current"), "implementationCommit":implementation,
    }
    (evidence / "environment.json").write_text(json.dumps(environment, indent=2) + "\n", encoding="utf-8")
    mapping = {
        "pageId":"PRODUCT-000", "implementationCommit":implementation,
        "conditions":[
            {"id":"PRODUCT-G6-B02","hubStatus":"PASS","externalStatus":"OPEN","evidence":["products-response.html","dependencies.json"],"boundary":"Fixed clean RFQ links are visible; receiver/form is external and not claimed."},
            {"id":"PRODUCT-G6-B03","hubStatus":"PASS","externalStatus":"OPEN","evidence":["readiness-matrix.json","seo-schema.json"],"boundary":"All fail-closed route states pass; actual target pages remain external."},
            {"id":"PRODUCT-G6-TDS-I02","hubStatus":"PASS","externalStatus":"NOT_APPLICABLE","evidence":["products-response.html","seo-schema.json"],"boundary":"14 visible summaries and Product descriptions share one server source."},
            {"id":"PRODUCT-G7-B05","hubStatus":"PASS","externalStatus":"RELEASE_OPEN","evidence":["seo-schema.json","products-response.html"],"boundary":"Local candidate remains noindex; Gate10 is not authorized."},
            {"id":"PRODUCT-G7-B06","hubStatus":"PASS","externalStatus":"OPEN","evidence":["readiness-matrix.json","identity.json","dependencies.json"],"boundary":"Malaysia scope is fail-closed; external target readiness remains open."},
            {"id":"PRODUCT-G7-B07","hubStatus":"PASS","externalStatus":"GATE9_REVIEW","evidence":["layout-1440.json","layout-1024.json","layout-768.json","layout-390.json","faq-keyboard-768.png","menu-390.png","cookie-390.png"],"boundary":"Automated and browser evidence passed; independent Gate9 remains pending."},
        ],
        "qualityStatus":"GATE8_CANDIDATE_READY_FOR_GATE9", "integrationStatus":"OPEN_EXTERNAL_DEPENDENCIES", "releaseStatus":"NOT_AUTHORIZED",
    }
    (evidence / "acceptance-mapping.json").write_text(json.dumps(mapping, indent=2) + "\n", encoding="utf-8")
    acceptance = """# PRODUCT-000 Gate 8 acceptance evidence\n\n- Candidate: `{implementation}`\n- Page quality: `GATE8_CANDIDATE_READY_FOR_GATE9`\n- Integration: `OPEN_EXTERNAL_DEPENDENCIES` (14 Grade, 2 Process, 3 Support, and RFQ receiver owners)\n- Release: `NOT_AUTHORIZED`; local runtime remains `noindex, nofollow`.\n- Scope: PRODUCT-000 Hub only. No child target page or RFQ receiver was created.\n\nThe complete automated suite passed against the isolated `d32-product-000` runtime. Browser evidence covers 1440/1024/768/390, exact content/module order, selector, FAQ, shared menu/cookie focus, 44px targets, overflow, and axe. Real WordPress fixtures cover zero/partial/full readiness and restore exactly. Page quality is reported separately from integration and release.\n""".format(implementation=implementation)
    (evidence / "acceptance.md").write_text(acceptance, encoding="utf-8")
    check_directory(evidence)


def write_receipt(evidence: Path, receipt: Path) -> None:
    check_clean(allow_evidence=True, evidence=evidence)
    check_directory(evidence)
    artifact = json.loads((evidence / "artifact.json").read_text(encoding="utf-8"))
    lines = [
        "# PRODUCT-000 Gate 8 handoff receipt", "",
        f"- Handoff: `PRODUCT-D32-G8-01`", f"- Gate 8 task: `01a0bd3a-a7ba-7632-bda0-fad444f654db`",
        f"- Implementation commit: `{artifact['implementation_commit']}`", f"- Build ID: `{artifact['build_id']}`",
        "- Runtime: `http://127.0.0.1:8232/products/` (`site_scope=tio2-my`, local preview)",
        "- Hold: `GATE9_PASS_OR_RETURN_NOTICE`", "- Page quality: `GATE8_CANDIDATE_READY_FOR_GATE9`",
        "- Integration: `OPEN_EXTERNAL_DEPENDENCIES`", "- Release: `NOT_AUTHORIZED`", "",
        "The Hub implementation, editable CMS data, scoped resolver, responsive interactions, and metadata are complete for Gate 9 review. External Grade/Process/Support targets and the RFQ receiver remain owned by their separate tasks; this receipt does not claim them ready. Gate 10/public deployment is outside scope.", "",
        "## Evidence", "",
    ]
    for path in sorted(evidence.iterdir()):
        if path.is_file(): lines.append("EVIDENCE: " + relative(path))
    receipt.parent.mkdir(parents=True, exist_ok=True)
    receipt.write_text("\n".join(lines) + "\n", encoding="utf-8")


def evidence_type(path: Path) -> str:
    if path.suffix.lower() in {".png", ".html"}: return "ACTUAL_RUNTIME"
    if path.name in {"artifact.json","identity.json","environment.json"}: return "IDENTITY"
    if path.name in {"source-hashes.json","acceptance.md","acceptance-mapping.json"}: return "SOURCE_INSPECTION"
    if path.name in {"dependencies.json","readiness-matrix.json","migration-results.json","editor-results.json"}: return "LOCAL_SIMULATION"
    return "TEST_RESULT"


def proves(path: Path) -> list[str]:
    name = path.name
    if name in {"dependencies.json","readiness-matrix.json"}: return ["PRODUCT-G6-B03","PRODUCT-G7-B06"]
    if name in {"products-response.html","seo-schema.json","products-content.json","products-content-before.json","products-content-restored.json"}: return ["PRODUCT-G6-TDS-I02","PRODUCT-G7-B05"]
    if name in {"artifact.json","identity.json","environment.json","source-hashes.json","home-response.html","home-content.json"}: return ["PRODUCT-G7-B06"]
    if name in {"acceptance.md","acceptance-mapping.json","test-results.json"}: return ACCEPTANCE_IDS
    if "editor" in name or "migration" in name: return ["PRODUCT-G6-TDS-I02","PRODUCT-G7-B06"]
    return ["PRODUCT-G7-B07"]


def manifest(evidence: Path, receipt: Path, output: Path) -> None:
    assert_runtime()
    check_clean()
    check_directory(evidence)
    references = sorted(relative(path) for path in evidence.iterdir() if path.is_file())
    receipt_lines = {line.removeprefix("EVIDENCE: ") for line in receipt.read_text(encoding="utf-8").splitlines() if line.startswith("EVIDENCE: ")}
    if receipt_lines != set(references): raise RuntimeError("Receipt evidence references do not match the evidence directory")
    artifact = json.loads((evidence / "artifact.json").read_text(encoding="utf-8"))
    identity = json.loads((evidence / "identity.json").read_text(encoding="utf-8"))
    evidence_head = git("rev-parse","HEAD")
    container_id = quiet(["docker","compose","ps","-q","wordpress"])
    started_at = quiet(["docker","inspect","--format","{{.State.StartedAt}}",container_id])
    environment = "Windows; Docker Compose d32-product-000; WordPress local preview; Chrome Playwright; http://127.0.0.1:8232"
    entries = []
    for path in sorted(evidence.iterdir()):
        if not path.is_file(): continue
        entries.append({"path":relative(path),"sha256":sha(path),"evidence_type":evidence_type(path),"proves":proves(path),"command":"python scripts/products-evidence.py run-suite/capture","environment":environment})
    data = {
        "schema_version":"gate8-evidence-manifest-v1.0", "handoff_id":"PRODUCT-D32-G8-01", "gate8_task_id":"01a0bd3a-a7ba-7632-bda0-fad444f654db", "site_scope":"tio2-my", "receipt_path":relative(receipt),
        "pages":[{"page_id":"PRODUCT-000","acceptance_condition_ids":ACCEPTANCE_IDS,"runtime_path":"/products/"}],
        "git":{"repository":ROOT.as_posix(),"branch":git("branch","--show-current"),"baseline_commit":BASELINE_COMMIT,"implementation_commit":artifact["implementation_commit"],"evidence_head":evidence_head,"clean_checked_at":utc_now()},
        "build":{"directory":artifact["directory"],"build_id":artifact["build_id"],"implementation_commit":artifact["implementation_commit"]},
        "runtime":{"base_url":"http://127.0.0.1:8232","site_scope":"tio2-my","environment_type":"local-preview","started_at":started_at,"hold_until":"GATE9_PASS_OR_RETURN_NOTICE","require_build_marker":False,"checks":[
            {"path":"/","expected_status":200,"contains":[f'<meta name="tio2-artifact" content="{artifact["build_id"]}">',f'<meta name="tio2-content-sha256" content="{identity["homeContentSha256"]}">',"Malaysia Titanium Dioxide for Industrial Buyers"]},
            {"path":"/products/","expected_status":200,"contains":[f'<meta name="tio2-artifact" content="{artifact["build_id"]}">',f'<meta name="tio2-products-content-sha256" content="{identity["productsContentSha256"]}">',"Titanium Dioxide Pigment Grades for Industrial Applications"]},
        ]},
        "evidence":entries,"receipt_evidence_references":references,
        "known_open_items":[
            {"id":"PRODUCT-G6-B02-EXTERNAL","owner":"CONV-RFQ target owner","blocking_layer":"INTEGRATION","closure_evidence":"Approved Malaysia RFQ route/form, privacy, validation, failure, success, and receiver evidence","closure_timing":"Before integration/release"},
            {"id":"PRODUCT-G6-B03-EXTERNAL","owner":"Fourteen Grade, two Process, APP-000, DOC-000, and MARKET-000 owners","blocking_layer":"INTEGRATION","closure_evidence":"Actual approved target pages resolve with matching Page IDs, canonicals, responses, and Malaysia scope","closure_timing":"Before integration/release"},
            {"id":"PRODUCT-G7-B07-INDEPENDENT","owner":"D23 independent Gate9 reviewer","blocking_layer":"PAGE_GATE9","closure_evidence":"Independent read-only visual/content/SEO/Schema/accessibility PASS or RETURN on the exact candidate","closure_timing":"During Gate9"},
            {"id":"PRODUCT-GATE10","owner":"User / release owner","blocking_layer":"RELEASE","closure_evidence":"Explicit deployment and indexing authorization plus release verification","closure_timing":"Before public deployment/indexing"},
        ],
    }
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(output)


def main() -> int:
    # Windows commonly inherits a legacy console codec (for example GBK),
    # while Playwright's line reporter emits Unicode status characters.
    # Keep captured logs and the orchestrator output deterministically UTF-8.
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    if hasattr(sys.stderr, "reconfigure"):
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=["check-directory","run-suite","capture","write-receipt","manifest"])
    parser.add_argument("--evidence-dir", type=Path, default=DEFAULT_EVIDENCE)
    parser.add_argument("--receipt", type=Path, default=DEFAULT_RECEIPT)
    parser.add_argument("--manifest", type=Path, default=DEFAULT_MANIFEST)
    args = parser.parse_args()
    evidence = args.evidence_dir if args.evidence_dir.is_absolute() else ROOT / args.evidence_dir
    receipt = args.receipt if args.receipt.is_absolute() else ROOT / args.receipt
    output = args.manifest if args.manifest.is_absolute() else ROOT / args.manifest
    try:
        if args.action == "check-directory": check_directory(evidence)
        elif args.action == "run-suite": suite(evidence)
        elif args.action == "capture": capture(evidence)
        elif args.action == "write-receipt": write_receipt(evidence, receipt)
        else: manifest(evidence, receipt, output)
        return 0
    except Exception as error:
        print(f"ERROR: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
