import json
import os
import urllib.parse
import urllib.request

from bs4 import BeautifulSoup

BASE_URL = os.environ.get("TEST_BASE_URL", "").rstrip("/")
PROJECT = os.environ.get("COMPOSE_PROJECT_NAME", "")
if BASE_URL != "http://127.0.0.1:8242" or PROJECT != "d32-conv-rfq-gate8":
    raise RuntimeError("RFQ HTTP test refuses a non-isolated runtime")

FORMAL_URL = "https://tio2products.com/request-a-quote/"
SEO_TITLE = "Request a Titanium Dioxide Quote | TiO2 Malaysia"
SEO_DESCRIPTION = "Request a titanium dioxide quotation from TiO2 Malaysia by providing your grade, application, quantity in metric tonnes and destination for review."


def fetch(query=None):
    url = BASE_URL + "/request-a-quote/"
    if query:
        url += "?" + urllib.parse.urlencode(query, doseq=True)
    response = urllib.request.urlopen(url)
    return response, response.read().decode()


response, html = fetch()
soup = BeautifulSoup(html, "html.parser")
assert response.status == 200
assert response.headers["X-Site-Scope"] == "tio2-my"
assert soup.html["lang"] == "en"
assert [node.get_text(" ", strip=True) for node in soup.select("h1")] == ["Request a Titanium Dioxide Quote"]
assert [node["data-module"] for node in soup.select("main > section")] == ["rfq-hero", "rfq-form", "rfq-other"]
assert [node.get_text(" ", strip=True) for node in soup.select(".rfq-breadcrumb a, .rfq-breadcrumb [aria-current='page']")] == ["Home", "Request a Quote"]

form = soup.select_one("form#rfq-form")
assert form is not None
controls = form.select("input[name], select[name], textarea[name]")
public_controls = [node for node in controls if node.get("name") not in {"action", "nonce", "company_website"}]
assert [node["name"] for node in public_controls] == [
    "grade_id", "application_id", "quantity_mt", "destination_country",
    "destination_port_city", "company_name", "contact_name", "business_email",
    "phone_whatsapp", "website", "additional_requirements",
]
assert not form.select("[name='quantity_unit']")
assert form.select_one("[data-quantity-unit]").get_text(" ", strip=True) == "Metric tonnes (MT)"
assert [node["name"] for node in public_controls if node.has_attr("required")] == [
    "grade_id", "application_id", "quantity_mt", "destination_country",
    "company_name", "contact_name", "business_email",
]
assert form.select_one("[name='destination_country']")["placeholder"] == "Enter the destination country"
assert [option.get_text(" ", strip=True) for option in form.select("[name='grade_id'] option")] == [
    "Select a product or grade", "M-350", "M-510", "M-896", "M-996", "M-2196",
    "M-895", "M-200", "M-108", "M-210", "M-340", "M-886", "M-52", "M-2377",
    "CR-901", "Not sure / Need help",
]
assert [option.get_text(" ", strip=True) for option in form.select("[name='application_id'] option")] == [
    "Select an application", "Coatings", "Plastics", "Masterbatch", "Printing Inks", "Paper",
    "Specialty Materials", "Other / Not sure",
]
assert not soup.select(".field-error:not(:empty), .validation-summary:not([hidden])")
page_links = {(node.get_text(" ", strip=True), node.get("href")) for node in soup.select("main a")}
assert ("Privacy Policy", "/privacy-policy/") in page_links
assert ("Request a Sample", "/request-sample/") in page_links
assert ("Request Documents", "/request-documents/") in page_links

assert soup.title.string == SEO_TITLE
assert soup.select_one("meta[name='description']")["content"] == SEO_DESCRIPTION
assert soup.select_one("meta[name='robots']")["content"] == "noindex, nofollow"
assert soup.select_one("link[rel='canonical']")["href"] == FORMAL_URL
og = {node["property"]: node["content"] for node in soup.select("meta[property^='og:']")}
assert og == {"og:type": "website", "og:url": FORMAL_URL, "og:site_name": "TiO₂ Malaysia", "og:title": SEO_TITLE, "og:description": SEO_DESCRIPTION}
scripts = soup.select("script[type='application/ld+json']")
assert len(scripts) == 1
graph = json.loads(scripts[0].string)["@graph"]
assert [node["@type"] for node in graph] == ["WebPage", "BreadcrumbList"]
assert graph[0]["name"] == SEO_TITLE and graph[0]["description"] == SEO_DESCRIPTION
assert graph[0]["url"] == FORMAL_URL and graph[0]["@id"] == FORMAL_URL + "#webpage"
assert graph[0]["inLanguage"] == "en" and graph[0]["breadcrumb"] == {"@id": FORMAL_URL + "#breadcrumb"}
assert [item["name"] for item in graph[1]["itemListElement"]] == ["Home", "Request a Quote"]

for forbidden in ["CONV-RFQ", "tio2-my", "source_page_id", "market_id", "receiver", "route_ready", "CURRENT"]:
    assert forbidden not in html
for forbidden in ["googletagmanager", "google-analytics", "recaptcha", "turnstile", "remarketing"]:
    assert forbidden not in html.lower()

prefill_query = {
    "grade_id": "m-2377",
    "application_id": "coatings",
    "destination_country": "Malaysia",
    "process_context": "sulfate",
}
_, prefill_html = fetch(prefill_query)
prefill = BeautifulSoup(prefill_html, "html.parser")
assert prefill.select_one("[name='grade_id'] option[selected]")["value"] == "m-2377"
assert prefill.select_one("[name='application_id'] option[selected]")["value"] == "coatings"
assert prefill.select_one("[name='destination_country']")["value"] == "Malaysia"
assert prefill.select_one("[name='additional_requirements']").get_text() == "Sulfate"
assert prefill.select_one("link[rel='canonical']")["href"] == FORMAL_URL
assert json.loads(prefill.select_one("script[type='application/ld+json']").string) == json.loads(scripts[0].string)

hostile = {
    "grade_id[]": "m-350",
    "application_id": "stale",
    "destination_country": "European Union",
    "market_id": "european-union",
    "source_page_id": "PRODUCT-M2377",
    "company_name": "Buyer query must not render",
    "business_email": "buyer-query@example.test",
    "process_context": "specialty-materials",
}
_, hostile_html = fetch(hostile)
hostile_soup = BeautifulSoup(hostile_html, "html.parser")
assert hostile_soup.select_one("[name='grade_id']").get("value") is None
assert hostile_soup.select_one("[name='grade_id'] option[selected]") is None
assert hostile_soup.select_one("[name='application_id'] option[selected]") is None
assert hostile_soup.select_one("[name='destination_country']").get("value", "") == ""
assert hostile_soup.select_one("[name='company_name']").get("value", "") == ""
assert hostile_soup.select_one("[name='business_email']").get("value", "") == ""
for forbidden in ["European Union", "european-union", "PRODUCT-M2377", "Buyer query must not render", "buyer-query@example.test"]:
    assert forbidden not in hostile_html
assert hostile_soup.select_one("link[rel='canonical']")["href"] == FORMAL_URL
assert json.loads(hostile_soup.select_one("script[type='application/ld+json']").string) == json.loads(scripts[0].string)

print("RFQ HTTP structure, form, links, safe prefill, privacy, and metadata assertions passed")
