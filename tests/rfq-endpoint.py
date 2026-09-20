import json
import os
import subprocess
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE_URL = os.environ.get("TEST_BASE_URL", "").rstrip("/")
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")
if BASE_URL != "http://127.0.0.1:8242" or PROJECT != "d32-conv-rfq-gate8":
    raise RuntimeError("RFQ endpoint test refuses a non-isolated runtime")

ENDPOINT = BASE_URL + "/wp-admin/admin-ajax.php"


def cli(*args: str, check: bool = True) -> str:
    result = subprocess.run(
        ["docker", "compose", "run", "--rm", "wpcli", *args],
        cwd=ROOT,
        text=True,
        encoding="utf-8",
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=check,
    )
    return result.stdout.strip()


def set_outcome(outcome: str) -> None:
    cli("option", "update", "tio2_rfq_test_receiver_outcome", outcome)
    cli("option", "update", "tio2_rfq_test_receiver_calls", "0")
    cli("transient", "delete", "--all")


def calls() -> int:
    value = cli("option", "get", "tio2_rfq_test_receiver_calls", check=False)
    return int(value or "0")


def request(method: str = "POST", data: dict | None = None, origin: str | None = BASE_URL):
    headers = {"Accept": "application/json"}
    body = None
    url = ENDPOINT
    if method == "GET" and data is not None:
        url += "?" + urllib.parse.urlencode(data)
    elif data is not None:
        body = urllib.parse.urlencode(data).encode()
        headers["Content-Type"] = "application/x-www-form-urlencoded"
    if origin is not None:
        headers["Origin"] = origin
    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req)
        status = response.status
        raw = response.read().decode()
    except urllib.error.HTTPError as error:
        status = error.code
        raw = error.read().decode()
    return status, json.loads(raw)


nonce = cli("eval", "echo wp_create_nonce('tio2_rfq_submit');")
valid = {
    "action": "tio2_rfq_submit",
    "nonce": nonce,
    "company_website": "",
    "grade_id": "m-350",
    "application_id": "coatings",
    "quantity_mt": "12.5",
    "destination_country": "Malaysia",
    "destination_port_city": "Port Klang",
    "company_name": "RFQ Test Company",
    "contact_name": "RFQ Test Buyer",
    "business_email": "rfq-probe@example.test",
    "phone_whatsapp": "+60 12 345 6789",
    "website": "https://example.test/procurement",
    "additional_requirements": "Synthetic non-confidential endpoint fixture.",
}
sensitive = [valid[key] for key in ("business_email", "phone_whatsapp", "website", "additional_requirements")]

try:
    set_outcome("success")
    status, payload = request("GET", {"action": "tio2_rfq_submit"})
    assert status == 405 and payload == {"state": "submission_unconfirmed", "fieldErrors": []}
    assert calls() == 0

    missing_nonce = valid | {"nonce": ""}
    status, payload = request(data=missing_nonce)
    assert status == 403 and payload["state"] == "submission_unconfirmed"
    assert calls() == 0

    status, payload = request(data=valid, origin="http://127.0.0.1:9999")
    assert status == 403 and payload["state"] == "submission_unconfirmed"
    assert calls() == 0

    status, payload = request(data=valid | {"company_website": "bot.example"})
    assert status == 400 and payload["state"] == "submission_unconfirmed"
    assert calls() == 0

    status, payload = request(data=valid | {"unexpected": "value"})
    assert status == 400 and payload["state"] == "submission_unconfirmed"
    assert calls() == 0

    status, payload = request(data=valid | {"additional_requirements": "x" * 33000})
    assert status == 413 and payload["state"] == "submission_unconfirmed"
    assert calls() == 0

    status, payload = request(data=valid)
    assert status == 200 and payload == {"state": "receipt_confirmed", "fieldErrors": []}, (status, payload)
    assert calls() == 1

    set_outcome("field_error")
    status, payload = request(data=valid)
    assert status == 422
    assert payload == {"state": "validation_failed", "fieldErrors": {"business_email": "Enter a business email in the format name@company.com."}}
    assert calls() == 1

    set_outcome("unavailable")
    status, payload = request(data=valid)
    assert status == 503 and payload == {"state": "service_unavailable", "fieldErrors": []}
    assert calls() == 0

    set_outcome("message_only")
    status, payload = request(data=valid)
    assert status == 502 and payload == {"state": "submission_unconfirmed", "fieldErrors": []}
    assert calls() == 1

    set_outcome("success")
    results = [request(data=valid) for _ in range(6)]
    assert [status for status, _ in results] == [200, 200, 200, 200, 200, 429]
    assert results[-1][1] == {"state": "submission_unconfirmed", "fieldErrors": []}
    assert calls() == 5

    public_text = json.dumps([payload for _, payload in results])
    for value in sensitive:
        assert value not in public_text
    logs = subprocess.run(
        ["docker", "compose", "logs", "wordpress", "--since", "10m"],
        cwd=ROOT,
        text=True,
        encoding="utf-8",
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=True,
    ).stdout
    for value in sensitive:
        assert value not in logs
finally:
    cli("option", "delete", "tio2_rfq_test_receiver_outcome", check=False)
    cli("option", "delete", "tio2_rfq_test_receiver_calls", check=False)
    cli("transient", "delete", "--all", check=False)

print("RFQ endpoint method, origin, nonce, honeypot, size, allowlist, rate, privacy, and fake receiver branches passed")
