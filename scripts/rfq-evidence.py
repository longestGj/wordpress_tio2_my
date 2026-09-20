"""Run and materialize privacy-safe CONV-RFQ Gate 8 evidence."""
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
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]
RUNTIME_DIR = ROOT / ".runtime" / "rfq-final"
EVIDENCE_DIR = ROOT / "docs" / "verification" / "request-a-quote"
MANIFEST_PATH = ROOT / ".runtime" / "handoff" / "request-a-quote" / "gate8_evidence_manifest.json"
BASELINE_COMMIT = "ce4147c7076112934b3dc6d8d97e983efb045f2f"
HANDOFF_ID = "CONV-RFQ-D32-G8-HANDOFF-01"
GATE8_TASK_ID = "01a0bd3a-a7ba-7632-bda0-fad444f654db"
RECEIPT_PATH = "docs/handoffs/CONV-RFQ-gate8.md"
AC_IDS = [f"RFQ-D32-AC-{number:02d}-{suffix}" for number, suffix in [
    (1,"IDENTITY-CONTENT"),(2,"FIELDS-OPTIONS"),(3,"VALIDATION-A11Y"),(4,"STATE-ACK"),(5,"PREFILL"),
    (6,"SERVER-SECURITY"),(7,"CMS-MIGRATION"),(8,"SCOPE-ISOLATION"),(9,"SHARED-CHROME"),(10,"SEO-SCHEMA"),
    (11,"RESPONSIVE-VISUAL"),(12,"EXTERNAL-ROUTES"),(13,"ANALYTICS"),(14,"RECEIVER-CONFIG"),(15,"EVIDENCE-HANDOFF"),
]]
DEP_IDS = [
    "RFQ-D32-DEP-01-PROVIDER","RFQ-D32-DEP-02-PRIVACY-OPS","RFQ-D32-DEP-03-PRIVACY-ROUTE",
    "RFQ-D32-DEP-04-SIBLING-ROUTES","RFQ-D32-DEP-05-COOKIE-CMP","RFQ-D32-DEP-06-ANALYTICS-CONSENT",
]


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run(command: list[str], env: dict[str, str], log_path: Path) -> dict:
    started = datetime.now(timezone.utc).isoformat()
    before = time.monotonic()
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, encoding="utf-8", errors="replace")
    output = []
    assert process.stdout is not None
    for line in process.stdout:
        output.append(line)
        console_encoding=sys.stdout.encoding or "utf-8"
        safe_line=line.encode(console_encoding,errors="replace").decode(console_encoding)
        print(safe_line,end="",flush=True)
    code = process.wait()
    duration = round(time.monotonic() - before, 3)
    rendered = "".join(output)
    log_path.write_text(rendered, encoding="utf-8")
    return {"command": subprocess.list2cmdline(command), "startedAt": started, "durationSeconds": duration, "exitCode": code, "log": log_path.relative_to(ROOT).as_posix()}


def suite() -> int:
    required = {
        "COMPOSE_PROJECT_NAME": "d32-conv-rfq-gate8",
        "TEST_BASE_URL": "http://127.0.0.1:8242",
        "TIO2_LOCAL_URL": "http://127.0.0.1:8242",
        "TIO2_RFQ_RECEIVER_MODE": "fake",
    }
    for key, expected in required.items():
        if os.environ.get(key) != expected:
            raise RuntimeError(f"{key} must be {expected!r} for final RFQ verification")
    RUNTIME_DIR.mkdir(parents=True, exist_ok=True)
    env = os.environ.copy()
    env["TEST_OUTPUT_DIR"] = ".runtime/rfq-final"
    env["RFQ_EVIDENCE_DIR"] = ".runtime/rfq-final"
    npx = "npx.cmd" if os.name == "nt" else "npx"
    py = sys.executable
    commands = [
        ("php-lint", ["docker","compose","exec","-T","wordpress","sh","-lc","find /workspace/wp-content/plugins/tio2-content /workspace/wp-content/themes/tio2-malaysia -name '*.php' -print0 | xargs -0 -n1 php -l"]),
        ("home-model", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/content-test.php"]),
        ("rfq-model", ["docker","compose","exec","-T","wordpress","php","/workspace/tests/php/rfq-model-test.php"]),
        ("rfq-submission", ["docker","compose","run","--rm","--no-deps","--entrypoint","php","wpcli","/workspace/tests/php/rfq-submission-test.php"]),
        ("rfq-route", ["docker","compose","run","--rm","wpcli","eval-file","/workspace/tests/php/rfq-route-test.php"]),
        ("rfq-migration", [py,"tests/rfq-migration.py"]),
        ("rfq-http", [py,"tests/rfq-http.py"]),
        ("rfq-endpoint", [py,"tests/rfq-endpoint.py"]),
        ("rfq-isolation", [py,"tests/rfq-isolation.py"]),
        ("home-http", [py,"tests/http-contract.py"]),
        ("identity", [py,"tests/identity.py"]),
        ("home-negative-runtime", [py,"tests/negative-runtime.py"]),
        ("portable", [py,"tests/portability-test.py"]),
        ("runtime-settings", [py,"tests/runtime-settings-test.py"]),
        ("provision-contract", [py,"tests/provision-contract.py"]),
        ("browser-editor", [npx,"playwright","test","tests/rfq-browser.spec.mjs","tests/browser.spec.mjs","tests/rfq-editor.spec.mjs","tests/editor.spec.mjs"]),
        ("evidence-contract", [py,"tests/rfq-evidence.py"]),
    ]
    results = []
    for label, command in commands:
        print(f"\n=== {label} ===", flush=True)
        result = run(command, env, RUNTIME_DIR / f"{label}.log")
        result["id"] = label
        results.append(result)
        if result["exitCode"] != 0:
            break
    record = {"candidateCommit": subprocess.check_output(["git","rev-parse","HEAD"],cwd=ROOT,text=True).strip(), "environment":"d32-conv-rfq-gate8 at http://127.0.0.1:8242; local fake receiver only", "results":results}
    (RUNTIME_DIR / "test-results.json").write_text(json.dumps(record,indent=2)+"\n",encoding="utf-8")
    return 0 if len(results)==len(commands) and all(item["exitCode"]==0 for item in results) else 1


def read_url(path: str) -> tuple[int, str, dict[str, str]]:
    request=urllib.request.Request("http://127.0.0.1:8242"+path,headers={"User-Agent":"CONV-RFQ-Gate8-Evidence/1.0"})
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        response=opener.open(request,timeout=10)
    except urllib.error.HTTPError as error:
        response=error
    with response:
        return response.status,response.read().decode(errors="replace"),dict(response.headers.items())


def contract_record() -> dict:
    status, html, headers=read_url("/request-a-quote/")
    soup=BeautifulSoup(html,"html.parser")
    form=soup.select_one("#rfq-form")
    controls=[node.get("name") for node in form.select("input[name],select[name],textarea[name]") if node.get("type")!="hidden"]
    graph=json.loads(soup.select_one('script[type="application/ld+json"]').string)["@graph"]
    return {
        "status":status,"siteScopeHeader":headers.get("X-Site-Scope"),"lang":soup.html.get("lang"),
        "h1":[node.get_text(" ",strip=True) for node in soup.select("h1")],
        "modules":[node.get("data-module") for node in soup.select("main>section")],"editableControls":controls,
        "fixedQuantityUnit":form.select_one("[data-quantity-unit]").get_text(" ",strip=True),
        "canonical":soup.select_one('link[rel="canonical"]').get("href"),"robots":soup.select_one('meta[name="robots"]').get("content"),
        "title":soup.title.string,"description":soup.select_one('meta[name="description"]').get("content"),
        "schemaTypes":[node["@type"] for node in graph],
        "externalLinks":{node.get_text(" ",strip=True):node.get("href") for node in soup.select("main a") if node.get_text(" ",strip=True) in {"Privacy Policy","Request a Sample","Request Documents"}},
        "containsInternalIdentifiers":any(value in html for value in ["CONV-RFQ","tio2-my","source_page_id","market_id","route_ready"]),
        "optionalAnalyticsPresent":any(value in html.lower() for value in ["googletagmanager","google-analytics","remarketing","recaptcha","turnstile"]),
    }


def acceptance_rows() -> tuple[list[dict], list[dict]]:
    evidence_common=["docs/verification/request-a-quote/test-results.json","docs/verification/request-a-quote/runtime-contract.json"]
    notes={
        AC_IDS[0]:"200 route, one H1, exact module order and approved editable copy verified.",
        AC_IDS[1]:"Eleven editable controls plus fixed MT, exact option order, requiredness, limits and helpers verified.",
        AC_IDS[2]:"Initial, 15 validation branches, focused linked summary, ARIA and keyboard/axe checks passed.",
        AC_IDS[3]:"Fake receiver matrix, explicit receipt, duplicate prevention, value retention and fail-closed states passed.",
        AC_IDS[4]:"Positive/negative prefill matrix and invariant clean canonical passed.",
        AC_IDS[5]:"Method/origin/nonce/size/honeypot/allowlist/rate/privacy negatives passed with zero receiver calls.",
        AC_IDS[6]:"Independent RFQ CMS record, revision guard, migration idempotence/rollback/resume and exact restore passed.",
        AC_IDS[7]:"Wrong/missing content scope and missing page scope returned controlled 503 twice and restored exactly.",
        AC_IDS[8]:"Shared Header/Footer/Menu/Cookie and Home regression passed; Products is absent from this branch and remains an integration dependency.",
        AC_IDS[9]:"Exact head and WebPage/BreadcrumbList graph passed; query values and local identity do not alter metadata.",
        AC_IDS[10]:"Eight widths, target geometry, axe, reduced motion and 200% reflow proxy passed; native browser zoom and physical touch remain for independent review.",
        AC_IDS[11]:"Exact Privacy/Sample/Documents links stay visible; all three destinations truthfully remain 404 dependencies.",
        AC_IDS[12]:"No analytics/tag manager/remarketing was added; no conversion event exists.",
        AC_IDS[13]:"Production adapter remains disabled/fail-closed without production-only mode, enable flag and runtime key; no recipient/secret is public.",
        AC_IDS[14]:"Implementation/build/runtime/content identities, committed evidence, hashes and machine handoff are recorded.",
    }
    partial={AC_IDS[8],AC_IDS[10]}
    rows=[]
    for ac in AC_IDS:
        rows.append({"id":ac,"status":"NOT_TESTED" if ac in partial else "PASS","evidence":evidence_common,"commands":["python scripts/rfq-evidence.py --run-suite"],"result":notes[ac],"ownerBoundary":"D32 Gate 8; independent Gate 9 must recheck the exact candidate"})
    owners=[
        ("RFQ operational owner","Production provider/account/key/recipient binding and mailbox receipt remain outside Gate 8."),
        ("Legal/Privacy + RFQ operational owner","Operational privacy controller/contact/retention/processors/transfers/rights/DPA parity remains open."),
        ("Legal/Privacy page owner","Privacy and shared legal routes remain 404 in this isolated branch."),
        ("CONV-SAMPLE / CONV-DOC owners","Sample and Documents request routes remain 404 in this isolated branch."),
        ("Shared consent owner","CMP/banner/settings and storage inventory integration remains open."),
        ("Shared consent + analytics owner","GA4/GTM are absent, so consent firing is currently not applicable and not tested; DEP-05 remains open."),
    ]
    deps=[{"id":dep,"status":"NOT_TESTED","evidence":["docs/verification/request-a-quote/dependencies.json"],"commands":["python scripts/rfq-evidence.py --generate"],"result":detail,"ownerBoundary":owner} for dep,(owner,detail) in zip(DEP_IDS,owners)]
    return rows,deps


def validate(directory: Path) -> list[str]:
    errors=[]
    try:data=json.loads((directory/"acceptance.json").read_text(encoding="utf-8"))
    except Exception as error:return [f"acceptance.json unavailable: {error}"]
    actual_ac={item.get("id") for item in data.get("acceptance",[])}
    actual_dep={item.get("id") for item in data.get("dependencies",[])}
    if actual_ac!=set(AC_IDS):errors.append(f"acceptance IDs differ: {sorted(actual_ac^set(AC_IDS))}")
    if actual_dep!=set(DEP_IDS):errors.append(f"dependency IDs differ: {sorted(actual_dep^set(DEP_IDS))}")
    for item in data.get("acceptance",[])+data.get("dependencies",[]):
        if item.get("status") not in {"PASS","FAIL","NOT_TESTED"}:errors.append(f"invalid status: {item.get('id')}")
        for key in ["evidence","commands","result","ownerBoundary"]:
            if not item.get(key):errors.append(f"missing {key}: {item.get('id')}")
    return errors


def generate() -> int:
    results=json.loads((RUNTIME_DIR/"test-results.json").read_text(encoding="utf-8"))
    if any(item["exitCode"]!=0 for item in results["results"]):raise RuntimeError("Final suite contains a failure")
    artifact_path=EVIDENCE_DIR/"artifact.json"
    if not artifact_path.is_file():raise RuntimeError("Run scripts/artifact.py for the RFQ evidence directory first")
    artifact=json.loads(artifact_path.read_text(encoding="utf-8"))
    review_path=EVIDENCE_DIR/"code-review.md"
    review=review_path.read_bytes() if review_path.is_file() else None
    EVIDENCE_DIR.mkdir(parents=True,exist_ok=True)
    for path in EVIDENCE_DIR.iterdir():
        if path.is_file():path.unlink()
    artifact_path.write_text(json.dumps(artifact,indent=2)+"\n",encoding="utf-8")
    if review is not None:review_path.write_bytes(review)
    safe_outputs=[
        "rfq-initial-1440.png","rfq-initial-768.png","rfq-initial-390.png","rfq-validation.png","rfq-pending.png",
        "rfq-failure.png","rfq-unavailable.png","rfq-success.png","rfq-menu-390.png","rfq-long-320.png",
        "rfq-editor-changed.png","rfq-editor-results.json","rfq-isolation.json","migration-results.json","playwright-results.json",
    ]
    for name in safe_outputs:
        source=RUNTIME_DIR/name
        if not source.is_file():raise RuntimeError(f"Missing final evidence output: {source}")
        shutil.copyfile(source,EVIDENCE_DIR/name)
    shutil.copyfile(RUNTIME_DIR/"test-results.json",EVIDENCE_DIR/"test-results.json")
    runtime_contract=contract_record()
    (EVIDENCE_DIR/"runtime-contract.json").write_text(json.dumps(runtime_contract,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    routes={}
    for path in ["/privacy-policy/","/request-sample/","/request-documents/","/products/"]:
        routes[path]=read_url(path)[0]
    deps={"routes":routes,"productsRegression":"NOT_TESTED / integration dependency because accepted Products feature is not present in this branch","productionReceiver":"NOT_TESTED / external root open","realSubmissionPerformed":False,"analyticsEnabled":False,"cmpIntegration":"NOT_TESTED / shared dependency open"}
    (EVIDENCE_DIR/"dependencies.json").write_text(json.dumps(deps,indent=2)+"\n",encoding="utf-8")
    entries=[]
    for folder in ["wp-content/plugins/tio2-content","wp-content/themes/tio2-malaysia"]:
        for path in sorted((ROOT/folder).rglob("*")):
            if path.is_file():entries.append({"path":path.relative_to(ROOT).as_posix(),"sha256":sha256(path)})
    (EVIDENCE_DIR/"source-hashes.json").write_text(json.dumps(entries,indent=2)+"\n",encoding="utf-8")
    identity=json.loads((RUNTIME_DIR/"identity.json").read_text(encoding="utf-8"))
    (EVIDENCE_DIR/"identity.json").write_text(json.dumps({**identity,"implementationCommit":artifact["implementation_commit"],"branch":subprocess.check_output(["git","branch","--show-current"],cwd=ROOT,text=True).strip(),"runtime":"http://127.0.0.1:8242/request-a-quote/"},indent=2)+"\n",encoding="utf-8")
    versions={
        "capturedAt":datetime.now(timezone.utc).isoformat(),"composeProject":"d32-conv-rfq-gate8","baseUrl":"http://127.0.0.1:8242","environmentType":"local-preview",
        "wordpress":subprocess.check_output(["docker","compose","--project-name","d32-conv-rfq-gate8","run","--rm","wpcli","core","version"],cwd=ROOT,text=True).strip().splitlines()[-1],
        "php":subprocess.check_output(["docker","compose","--project-name","d32-conv-rfq-gate8","exec","-T","wordpress","php","-r","echo PHP_VERSION;"],cwd=ROOT,text=True).strip(),
        "receiverMode":"fake local deterministic fixture; no external submission or email","publicOrigin":"https://tio2products.com/","holdUntil":"GATE9_PASS_OR_RETURN_NOTICE",
    }
    (EVIDENCE_DIR/"environment.json").write_text(json.dumps(versions,indent=2)+"\n",encoding="utf-8")
    acceptance,deps_rows=acceptance_rows()
    (EVIDENCE_DIR/"acceptance.json").write_text(json.dumps({"acceptance":acceptance,"dependencies":deps_rows},ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    lines=["# CONV-RFQ Gate 8 acceptance mapping","","Implementation self-check only; independent Gate 9 remains required.","","## Acceptance","","| ID | Status | Result |","|---|---|---|"]
    lines += [f"| `{item['id']}` | `{item['status']}` | {item['result']} |" for item in acceptance]
    lines += ["","## Dependencies","","| ID | Status | Owner / result |","|---|---|---|"]
    lines += [f"| `{item['id']}` | `{item['status']}` | {item['ownerBoundary']}: {item['result']} |" for item in deps_rows]
    (EVIDENCE_DIR/"acceptance.md").write_text("\n".join(lines)+"\n",encoding="utf-8")
    (EVIDENCE_DIR/"receiver-security.json").write_text(json.dumps({"mode":"local fake only","externalRequests":0,"realEmailOrSubmission":False,"explicitReceiptRequired":True,"noAutomaticRetry":True,"publicEnvelope":["state","fieldErrors"],"productionConfiguration":"fail-closed unless production-only mode, enable flag and runtime key are all present","recipientOrSecretInEvidence":False},indent=2)+"\n",encoding="utf-8")
    errors=validate(EVIDENCE_DIR)
    if errors:raise RuntimeError("; ".join(errors))
    print(f"Generated privacy-safe RFQ evidence at {EVIDENCE_DIR}")
    return 0


def evidence_metadata(path: Path) -> tuple[str, list[str], str]:
    """Return conservative evidence metadata for the Gate 8 machine manifest."""
    name=path.name
    all_ids=AC_IDS+DEP_IDS
    if path.suffix.lower()==".png":
        proves=[AC_IDS[10]]
        if "initial" in name:proves=[AC_IDS[0],AC_IDS[1],AC_IDS[10]]
        elif "validation" in name:proves=[AC_IDS[2],AC_IDS[10]]
        elif any(state in name for state in ["pending","failure","unavailable","success"]):proves=[AC_IDS[3],AC_IDS[10]]
        elif "menu" in name:proves=[AC_IDS[8],AC_IDS[10]]
        elif "editor" in name:proves=[AC_IDS[6]]
        return "STATIC_VISUAL",proves,"npx playwright test tests/rfq-browser.spec.mjs tests/rfq-editor.spec.mjs"
    mapping={
        "acceptance.json":("TEST_RESULT",all_ids,"python scripts/rfq-evidence.py --generate"),
        "acceptance.md":("TEST_RESULT",all_ids,"python scripts/rfq-evidence.py --generate"),
        "artifact.json":("IDENTITY",[AC_IDS[14]],"python scripts/artifact.py --evidence-dir docs/verification/request-a-quote"),
        "code-review.md":("SOURCE_INSPECTION",[AC_IDS[5],AC_IDS[6],AC_IDS[7],AC_IDS[13],AC_IDS[14]],"git diff --check ce4147c7076112934b3dc6d8d97e983efb045f2f..f407f0ee526ac3d8fe1dc3efefa31364dc191030"),
        "dependencies.json":("ACTUAL_RUNTIME",[AC_IDS[8],AC_IDS[11],AC_IDS[12],AC_IDS[13]]+DEP_IDS,"python scripts/rfq-evidence.py --generate"),
        "environment.json":("IDENTITY",[AC_IDS[14]],"python scripts/rfq-evidence.py --generate"),
        "identity.json":("IDENTITY",[AC_IDS[0],AC_IDS[14]],"python tests/identity.py"),
        "migration-results.json":("TEST_RESULT",[AC_IDS[6]],"python tests/rfq-migration.py"),
        "playwright-results.json":("TEST_RESULT",[AC_IDS[2],AC_IDS[3],AC_IDS[4],AC_IDS[6],AC_IDS[8],AC_IDS[10]],"npx playwright test tests/rfq-browser.spec.mjs tests/browser.spec.mjs tests/rfq-editor.spec.mjs tests/editor.spec.mjs"),
        "receiver-security.json":("RECEIVER",[AC_IDS[3],AC_IDS[5],AC_IDS[13]],"python scripts/rfq-evidence.py --generate"),
        "rfq-editor-results.json":("TEST_RESULT",[AC_IDS[6]],"npx playwright test tests/rfq-editor.spec.mjs"),
        "rfq-isolation.json":("TEST_RESULT",[AC_IDS[7]],"python tests/rfq-isolation.py"),
        "runtime-contract.json":("ACTUAL_RUNTIME",[AC_IDS[0],AC_IDS[1],AC_IDS[9],AC_IDS[11],AC_IDS[12],AC_IDS[14]],"python scripts/rfq-evidence.py --generate"),
        "source-hashes.json":("SOURCE_INSPECTION",[AC_IDS[14]],"python scripts/rfq-evidence.py --generate"),
        "test-results.json":("TEST_RESULT",AC_IDS,"python scripts/rfq-evidence.py --run-suite"),
    }
    if name not in mapping:raise RuntimeError(f"No manifest metadata for evidence file: {name}")
    return mapping[name]


def manifest() -> int:
    dirty=subprocess.check_output(["git","status","--porcelain"],cwd=ROOT,text=True).strip()
    if dirty:raise RuntimeError(f"Manifest requires a clean evidence HEAD; current status: {dirty}")
    evidence_head=subprocess.check_output(["git","rev-parse","HEAD"],cwd=ROOT,text=True).strip()
    branch=subprocess.check_output(["git","branch","--show-current"],cwd=ROOT,text=True).strip()
    artifact=json.loads((EVIDENCE_DIR/"artifact.json").read_text(encoding="utf-8"))
    evidence=[]
    for path in sorted(EVIDENCE_DIR.iterdir()):
        if not path.is_file():continue
        evidence_type,proves,command=evidence_metadata(path)
        evidence.append({
            "path":path.relative_to(ROOT).as_posix(),"sha256":sha256(path),"evidence_type":evidence_type,
            "proves":proves,"command":command,"environment":"d32-conv-rfq-gate8 local-preview at http://127.0.0.1:8242; fake receiver only",
        })
    container_id=subprocess.check_output(["docker","compose","--project-name","d32-conv-rfq-gate8","ps","-q","wordpress"],cwd=ROOT,text=True).strip()
    if not container_id:raise RuntimeError("WordPress runtime is not running")
    started_at=subprocess.check_output(["docker","inspect","-f","{{.State.StartedAt}}",container_id],cwd=ROOT,text=True).strip()
    known=[
        {"id":AC_IDS[8],"owner":"Products integration owner and Gate 9","blocking_layer":"INTEGRATION","closure_evidence":"Same-branch Products page plus shared Chrome regression on the integrated candidate.","closure_timing":"Before develop integration is accepted."},
        {"id":AC_IDS[10],"owner":"Independent Gate 9","blocking_layer":"PAGE_GATE9","closure_evidence":"Native 200% browser zoom and physical touch review on the fixed candidate.","closure_timing":"During independent Gate 9."},
        {"id":DEP_IDS[0],"owner":"RFQ operational owner","blocking_layer":"RELEASE","closure_evidence":"Production provider/account/key/recipient binding and controlled receipt test.","closure_timing":"Before production release; never during this Gate 8."},
        {"id":DEP_IDS[1],"owner":"Legal/Privacy and RFQ operational owner","blocking_layer":"RELEASE","closure_evidence":"Approved operational privacy controller, contact, retention, processor, transfer, rights and DPA evidence.","closure_timing":"Before production release."},
        {"id":DEP_IDS[2],"owner":"Legal/Privacy page owner","blocking_layer":"INTEGRATION","closure_evidence":"Working /privacy-policy/ route with approved content.","closure_timing":"Before integrated publication."},
        {"id":DEP_IDS[3],"owner":"CONV-SAMPLE and CONV-DOC owners","blocking_layer":"INTEGRATION","closure_evidence":"Working /request-sample/ and /request-documents/ routes.","closure_timing":"Before integrated publication."},
        {"id":DEP_IDS[4],"owner":"Shared consent owner","blocking_layer":"RELEASE","closure_evidence":"Approved CMP/banner/settings and storage inventory integration.","closure_timing":"Before production release if optional storage is enabled."},
        {"id":DEP_IDS[5],"owner":"Shared consent and analytics owners","blocking_layer":"RELEASE","closure_evidence":"Consent-aware analytics test, or an explicit decision to ship without analytics.","closure_timing":"Before production release; currently no analytics is present."},
    ]
    record={
        "schema_version":"gate8-evidence-manifest-v1.0","handoff_id":HANDOFF_ID,"gate8_task_id":GATE8_TASK_ID,"site_scope":"tio2-my","receipt_path":RECEIPT_PATH,
        "pages":[{"page_id":"CONV-RFQ","acceptance_condition_ids":AC_IDS,"runtime_path":"/request-a-quote/"}],
        "git":{"repository":str(ROOT.resolve()),"branch":branch,"baseline_commit":BASELINE_COMMIT,"implementation_commit":artifact["implementation_commit"],"evidence_head":evidence_head,"clean_checked_at":datetime.now(timezone.utc).isoformat()},
        "build":{"directory":artifact["directory"],"build_id":artifact["build_id"],"implementation_commit":artifact["implementation_commit"]},
        "runtime":{"base_url":"http://127.0.0.1:8242","site_scope":"tio2-my","environment_type":"local-preview","started_at":started_at,"hold_until":"GATE9_PASS_OR_RETURN_NOTICE","require_build_marker":False,"checks":[{"path":"/request-a-quote/","expected_status":200,"contains":["Request a Titanium Dioxide Quote",artifact["build_id"],"02f88b1ca3d539e406d2f020a550e2b327229e90b57d234787400742d7aff398"]}]},
        "evidence":evidence,"receipt_evidence_references":[item["path"] for item in evidence],"known_open_items":known,
    }
    MANIFEST_PATH.parent.mkdir(parents=True,exist_ok=True)
    MANIFEST_PATH.write_text(json.dumps(record,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print(MANIFEST_PATH)
    return 0


def main() -> int:
    parser=argparse.ArgumentParser()
    parser.add_argument("--run-suite",action="store_true")
    parser.add_argument("--generate",action="store_true")
    parser.add_argument("--validate-dir",type=Path)
    parser.add_argument("--manifest",action="store_true")
    args=parser.parse_args()
    if sum([args.run_suite,args.generate,args.validate_dir is not None,args.manifest])!=1:parser.error("choose exactly one action")
    if args.run_suite:return suite()
    if args.generate:return generate()
    if args.manifest:return manifest()
    errors=validate(args.validate_dir)
    if errors:
        print("\n".join(errors),file=sys.stderr);return 2
    print("RFQ evidence contract passed with exactly 15 acceptance IDs and 6 dependency IDs")
    return 0


if __name__=="__main__":raise SystemExit(main())
