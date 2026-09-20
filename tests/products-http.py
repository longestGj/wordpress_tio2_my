import json
import os
import urllib.request
from pathlib import Path

from bs4 import BeautifulSoup

BASE_URL = os.environ.get("TEST_BASE_URL", "http://127.0.0.1:8232").rstrip("/")
if BASE_URL != "http://127.0.0.1:8232":
    raise RuntimeError("Products HTTP test refuses a non-isolated runtime")


def page(path: str):
    response = urllib.request.urlopen(BASE_URL + path)
    html = response.read().decode()
    return response, html, BeautifulSoup(html, "html.parser")


home_response, home_html, home = page("/")
products_response, products_html, products = page("/products/")

assert home_response.status == products_response.status == 200
assert home.select_one('link[rel="canonical"]')["href"] == "https://tio2products.com/"
assert home.select_one('meta[property="og:url"]')["content"] == "https://tio2products.com/"
assert "tio2malaysia.com" not in home_html
assert "assets/products.css" not in home_html and "assets/products.js" not in home_html
assert [link.get_text(strip=True) for link in home.select('.desktop-nav a[aria-current="page"]')] == ["Home"]
assert [link.get_text(strip=True) for link in home.select('.mobile-menu nav a[aria-current="page"]')] == ["Home"]

assert products.html["lang"] == "en"
assert products.title.string == "Titanium Dioxide Pigment Grades | TiO2 Malaysia"
assert products.select_one('meta[name="description"]')["content"] == (
    "Explore 14 titanium dioxide pigment grades by application, production process and portfolio group, "
    "then continue to grade pages for technical evaluation."
)
assert products.select_one('link[rel="canonical"]')["href"] == "https://tio2products.com/products/"
assert products.select_one('meta[property="og:url"]')["content"] == "https://tio2products.com/products/"
assert "assets/products.css" in products_html and "assets/products.js" in products_html
assert not products.select('meta[property="og:image"]')
assert "noindex" in products.select_one('meta[name="robots"]')["content"]
assert [link.get_text(strip=True) for link in products.select('.desktop-nav a[aria-current="page"]')] == ["Products"]
assert [link.get_text(strip=True) for link in products.select('.mobile-menu nav a[aria-current="page"]')] == ["Products"]
assert [heading.get_text(" ", strip=True) for heading in products.select("h1")] == [
    "Titanium Dioxide Pigment Grades for Industrial Applications"
]
assert products.select_one('a[href="#grade-selector"]')
assert [section["data-module"] for section in products.select("main > section")] == [
    "breadcrumb",
    "hero",
    "selector",
    "process",
    "directory",
    "evaluation",
    "faq",
    "final-rfq",
]

rows = products.select(".product-grade-row")
assert len(rows) == 14
names = [row.select_one(".product-grade-name").get_text(strip=True) for row in rows]
assert names == ["M-350","M-510","M-896","M-996","M-2196","M-895","M-200","M-108","M-210","M-340","M-886","M-52","M-2377","CR-901"]
assert len(set(names)) == 14
assert [len(group.select(".product-grade-row")) for group in products.select(".product-grade-group")] == [6,5,2,1]

defaults = json.loads(open("wp-content/plugins/tio2-content/products-defaults.json", encoding="utf-8").read())["fields"]
assert [row.select_one("p").get_text(strip=True) for row in rows] == [
    defaults[f"directory.grade.{number}.summary"] for number in range(1, 15)
]
assert len(products.select(".product-evaluation-step")) == 5
assert len(products.select(".product-faq-item")) == 5
assert len(products.select(".product-faq-answer")) == 5
assert not products.select("[data-route-key] a, a[data-route-key]")
assert not products.select('[data-module="support"]')
assert products.select_one(".product-special-process strong").get_text(strip=True) == "CR-901 · Vapor-phase oxidation"
assert products.select('a[href="/request-a-quote/"]')
assert not products.select('a[href^="/contact"]')

lower = products_html.lower()
for forbidden in [
    "summary_source_id", "relationship_version", "content_revision", "gate 6", "gate 7",
    "add to cart", "buy now", "unit price", "in stock", "free shipping", "rubber",
    "googletagmanager", "google-analytics",
]:
    assert forbidden not in lower, forbidden

scripts = products.select('script[type="application/ld+json"]')
assert len(scripts) == 1
graph = json.loads(scripts[0].string)["@graph"]
types = [node["@type"] for node in graph]
assert types == ["WebSite", "Organization", "Brand", "CollectionPage", "BreadcrumbList", "ItemList", "FAQPage"]
item_list = graph[5]
assert item_list["numberOfItems"] == 14
assert [item["position"] for item in item_list["itemListElement"]] == list(range(1, 15))
assert [item["item"]["name"] for item in item_list["itemListElement"]] == names
assert all("url" not in item["item"] and "@id" not in item["item"] for item in item_list["itemListElement"])
assert all(item["item"]["description"] == rows[index].select_one("p").get_text(strip=True) for index, item in enumerate(item_list["itemListElement"]))
assert "http://127.0.0.1:8232" not in scripts[0].string
assert "tio2malaysia.com" not in scripts[0].string

if os.environ.get("PRODUCT_EVIDENCE_DIR"):
    evidence = Path(os.environ["PRODUCT_EVIDENCE_DIR"])
    evidence.mkdir(parents=True, exist_ok=True)
    (evidence / "products-response.html").write_text(products_html, encoding="utf-8")
    (evidence / "seo-schema.json").write_text(json.dumps({
        "title": products.title.string,
        "description": products.select_one('meta[name="description"]')["content"],
        "canonical": products.select_one('link[rel="canonical"]')["href"],
        "robots": products.select_one('meta[name="robots"]')["content"],
        "graphTypes": types,
        "itemNames": names,
        "schemaUrlCount": sum(1 for item in item_list["itemListElement"] if item["item"].get("url")),
    }, indent=2) + "\n", encoding="utf-8")

print("Products HTTP, content, route-state, identity, and Schema assertions passed")
