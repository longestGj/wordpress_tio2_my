import hashlib
import json
import os
import subprocess
import urllib.request
from pathlib import Path

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("TEST_BASE_URL", "http://127.0.0.1:8232").rstrip("/")
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")
EVIDENCE = Path(os.environ.get("PRODUCT_EVIDENCE_DIR", ".runtime/products-readiness"))
if BASE_URL != "http://127.0.0.1:8232" or PROJECT != "d32-product-000":
    raise RuntimeError("Products readiness test refuses a non-isolated runtime")

GRADE_KEYS = ["GRADE-M350","GRADE-M510","GRADE-M896","GRADE-M996","GRADE-M2196","GRADE-M895","GRADE-M200","GRADE-M108","GRADE-M210","GRADE-M340","GRADE-M886","GRADE-M52","GRADE-M2377","GRADE-CR901"]
EXPECTED_NAMES = ["M-350","M-510","M-896","M-996","M-2196","M-895","M-200","M-108","M-210","M-340","M-886","M-52","M-2377","CR-901"]


def cli(*args: str) -> str:
    return subprocess.run(
        ["docker", "compose", "run", "--rm", "wpcli", *args], cwd=ROOT, text=True,
        encoding="utf-8", stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True,
    ).stdout.strip()


def fixture(action: str, *args: str):
    output = cli("eval-file", "/workspace/tests/php/products-readiness-fixture.php", action, *args)
    return json.loads(output) if output else None


def option(name: str):
    return json.loads(cli("option", "get", name, "--format=json"))


def digest(value) -> str:
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def capture(label: str) -> dict:
    html = urllib.request.urlopen(BASE_URL + "/products/").read().decode()
    soup = BeautifulSoup(html, "html.parser")
    graph = json.loads(soup.select_one('script[type="application/ld+json"]').string)["@graph"]
    item_list = next(node for node in graph if node["@type"] == "ItemList")
    items = item_list["itemListElement"]
    state = {
        "label": label,
        "modules": [node["data-module"] for node in soup.select("main > section")],
        "directoryActions": len(soup.select(".product-grade-row a[data-route-key]")),
        "processCards": len(soup.select(".product-process-cards .product-route-card")),
        "supportCards": len(soup.select(".product-support-cards .product-route-card")),
        "supportPresent": bool(soup.select_one('[data-module="support"]')),
        "crAction": bool(soup.select_one('.product-special-process a[data-route-key="GRADE-CR901"]')),
        "itemNames": [item["item"]["name"] for item in items],
        "schemaUrls": [item["item"].get("url") for item in items if item["item"].get("url")],
        "schemaIds": [item["item"].get("@id") for item in items if item["item"].get("@id")],
    }
    assert state["itemNames"] == EXPECTED_NAMES
    assert len(items) == 14 and [item["position"] for item in items] == list(range(1, 15))
    return state


homepage_hash = digest(option("tio2_content"))
products_hash = digest(option("tio2_products_content"))
managed = fixture("managed-status")
states = []
fixture("purge-stale")
try:
    assert fixture("assert-clear")["occupied"] == []
    zero = capture("zero")
    states.append(zero)
    assert zero["directoryActions"] == zero["processCards"] == zero["supportCards"] == 0
    assert zero["supportPresent"] is False and zero["crAction"] is False and zero["schemaUrls"] == []

    fixture("create", "GRADE-M350", "tio2-a")
    wrong_scope = capture("wrong-scope")
    states.append(wrong_scope)
    assert wrong_scope["directoryActions"] == 0 and wrong_scope["schemaUrls"] == []
    fixture("delete", "GRADE-M350")

    fixture("create", "GRADE-CR901", "tio2-a")
    assert capture("cr-wrong-scope")["crAction"] is False
    fixture("delete", "GRADE-CR901")
    fixture("create", "GRADE-CR901", "tio2-my")
    cr_ready = capture("cr-ready-independent")
    states.append(cr_ready)
    assert cr_ready["crAction"] is True and cr_ready["directoryActions"] == 1 and len(cr_ready["schemaUrls"]) == 1
    fixture("delete", "GRADE-CR901")
    assert capture("cr-removed")["crAction"] is False

    for key in GRADE_KEYS[:3]: fixture("create", key, "tio2-my")
    partial = capture("three-grades")
    states.append(partial)
    assert partial["directoryActions"] == 3 and len(partial["schemaUrls"]) == len(partial["schemaIds"]) == 3
    assert all(url.startswith("https://tio2products.com/products/") for url in partial["schemaUrls"])
    for key in GRADE_KEYS[3:]: fixture("create", key, "tio2-my")
    grades_full = capture("fourteen-grades")
    states.append(grades_full)
    assert grades_full["directoryActions"] == 14 and len(grades_full["schemaUrls"]) == len(grades_full["schemaIds"]) == 14

    fixture("create", "PRODUCT-PROC-CL", "tio2-my")
    process_one = capture("process-one"); states.append(process_one); assert process_one["processCards"] == 1
    fixture("create", "PRODUCT-PROC-SU", "tio2-my")
    process_two = capture("process-two"); states.append(process_two); assert process_two["processCards"] == 2
    fixture("delete", "PRODUCT-PROC-SU"); assert capture("process-back-to-one")["processCards"] == 1
    fixture("delete", "PRODUCT-PROC-CL"); assert capture("process-zero")["processCards"] == 0

    for expected, key in enumerate(["APP-000","DOC-000","MARKET-000"], start=1):
        fixture("create", key, "tio2-my")
        support = capture(f"support-{expected}"); states.append(support)
        assert support["supportCards"] == expected and support["supportPresent"] is True
    fixture("delete", "MARKET-000"); assert capture("support-back-to-two")["supportCards"] == 2
    fixture("delete", "DOC-000"); assert capture("support-back-to-one")["supportCards"] == 1
    fixture("delete", "APP-000")
    support_zero = capture("support-zero"); assert support_zero["supportCards"] == 0 and support_zero["supportPresent"] is False

    for key in ["PRODUCT-PROC-CL","PRODUCT-PROC-SU","APP-000","DOC-000","MARKET-000"]: fixture("create", key, "tio2-my")
    full = capture("full-registry"); states.append(full)
    assert full["directoryActions"] == 14 and full["processCards"] == 2 and full["supportCards"] == 3 and full["crAction"] is True
    assert full["modules"] == ["breadcrumb","hero","selector","process","directory","evaluation","support","faq","final-rfq"]
finally:
    fixture("cleanup")

restored = capture("restored-zero")
assert restored["directoryActions"] == restored["processCards"] == restored["supportCards"] == 0
assert digest(option("tio2_content")) == homepage_hash
assert digest(option("tio2_products_content")) == products_hash
assert fixture("managed-status") == managed
EVIDENCE.mkdir(parents=True, exist_ok=True)
(EVIDENCE / "readiness-matrix.json").write_text(json.dumps({"states": states, "restored": restored}, indent=2) + "\n", encoding="utf-8")
print("Products zero/partial/full readiness, scope isolation, Schema gating, and exact restoration passed")
