# PRODUCT-000 Products Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved English `/products/` WordPress hub with editable content, fail-closed route readiness, shared chrome/public identity, responsive interactions, and a machine-verifiable Gate8 evidence package.

**Architecture:** Keep the accepted homepage `tio2_content` option and schema unchanged. Add an independent, typed `tio2_products_content` option plus an idempotent managed-page migration, then render `page-products.php` from that content and a Malaysia-scoped route registry. Shared theme helpers own public URL, navigation current state, Global Chrome, SEO/Schema, and runtime identity; product-specific CSS/JS is isolated and loaded only for `PRODUCT-000`.

**Tech Stack:** WordPress 7.1, PHP 8.3, MariaDB 11.4, native CSS/JavaScript, Python 3 + BeautifulSoup, Playwright + axe-core, Docker Compose.

**Spec:** `D:/23MySec/pages/products/06_handoff/PRODUCT-000_D32_GATE6_HANDOFF_PACKAGE_V0.1.md` (SHA256 `0023743afc8cd1919ad6d05e274e03b721ba31f7248ecb639edb8975eb82fba2`), with exact content and behavior from the referenced Gate7 V0.3/V0.4 contracts and Gate4/5 frozen visual source.

## Global Constraints

- Scope is only `PRODUCT-000`, English `/products/`, `site_scope=tio2-my`; do not create Grade, Process, RFQ, Application, Documents, or Market target pages.
- Preserve the existing homepage, its `tio2_content` option, CMS edits, shared Header/Footer/Menu/Cookie Settings, and historical homepage evidence.
- The formal public origin is `https://tio2products.com/`; local request URLs and WordPress `home` never become canonical, Open Graph, or Schema identity.
- All fourteen grades remain visible in approved order and 6/5/2/1 groups. Only Malaysia-scoped, actually ready targets receive actions or Schema URLs.
- Process cards render atomically in 2/1/0 states; the CR-901 classification always remains and its action is independently gated. Support cards render atomically in 3/2/1/0 states.
- RFQ links always render as the clean `/request-a-quote/` URL. Default Coatings is not an explicit prefill selection, and this Hub does not implement the receiver.
- The 14 directory summaries are one editable server-side source for visible copy and optional Product Schema descriptions; no governance identifiers reach public output.
- Development and Gate8 stay `noindex, nofollow`; public deployment, indexing, Gate10, and external receiver readiness remain out of scope.
- At 390px the H1 is 36px/700, zero letter spacing, and may wrap naturally to four lines.
- Evidence writes only to `docs/verification/products/` or ignored `.runtime/`; never overwrite `docs/verification/home/`.
- Every Compose and browser command runs with `COMPOSE_PROJECT_NAME=d32-product-000` and `TEST_BASE_URL=http://127.0.0.1:8232`; modifying tests must refuse any other runtime.

## Review Focus

- A missing or foreign-scope route must remove only its approved action/card and must never borrow another path; real WordPress pages with wrong/missing metadata exercise this.
- Re-running migration after CMS edits must preserve both homepage and Products edits; rollback/resume must affect only the managed Products option/page.
- Selector rerenders must keep grade/action association, omit links for unready grades, preserve focus, and never create query/facet URLs.
- JSON-LD must retain fourteen ordered identities at zero/partial/full readiness while adding URL/`@id` only for actually ready items.
- Shared navigation must expose one current link on the active surface, with homepage and Products current states both regressed at desktop/mobile widths.

---

### Task 1: Shared Public Identity and Additive Products Migration

**Files:**
- Create: `wp-content/plugins/tio2-content/includes/products.php`
- Create: `wp-content/plugins/tio2-content/products-schema.json`
- Create: `wp-content/plugins/tio2-content/products-defaults.json`
- Create: `scripts/products-migration.php`
- Create: `tests/php/runtime-config-test.php`
- Create: `tests/php/products-model-test.php`
- Create: `tests/products-migration.py`
- Modify: `wp-content/plugins/tio2-content/tio2-content.php`
- Modify: `wp-content/themes/tio2-malaysia/inc/seo.php`
- Modify: `compose.yaml`

**Interfaces:**
- Consumes: existing `tio2_content`, `TIO2_SITE_SCOPE`, `TIO2_PUBLIC_URL`, WordPress page/meta APIs.
- Produces: `tio2_public_base_url(): string`, `tio2_products_content(): array`, `tio2_products_field(string): string`, `tio2_route_registry(): array`, `tio2_resolve_route(string): ?array`, managed page meta `_tio2_page_id=PRODUCT-000`, migration status/rollback/resume commands.

- [ ] **Step 1: Write failing configuration and model tests**

`runtime-config-test.php` stubs WordPress URL helpers, defines `TIO2_PUBLIC_URL=https://tio2products.com`, and asserts `tio2_public_base_url()` returns exactly `https://tio2products.com/` even when `home_url('/')` is `http://127.0.0.1:8232/`. It rejects non-HTTPS, path-bearing, credential-bearing, query, fragment, and old-domain values.

`products-model-test.php` loads the products schema/defaults and asserts `site_scope=tio2-my`, schema version 1, exact field set, fourteen ordered grade records, exact 6/5/2/1 grouping, exact summary strings, six application sets 8/8/7/4/2/1, five steps, and five FAQs.

- [ ] **Step 2: Run tests and confirm RED**

Run:

```powershell
$env:COMPOSE_PROJECT_NAME = 'd32-product-000'
$env:TEST_BASE_URL = 'http://127.0.0.1:8232'
docker compose exec -T wordpress php /workspace/tests/php/runtime-config-test.php
docker compose exec -T wordpress php /workspace/tests/php/products-model-test.php
```

Expected: non-zero because the shared public URL helper and product model do not exist.

- [ ] **Step 3: Implement the isolated product model and strict public URL**

Create a flat typed schema/default JSON pair for all buyer-visible Products copy and paths. Validate required identity and the exact schema key set, but never compare edited values with historical approved text at runtime. Implement a route registry whose fixed internal keys/Page IDs map to editable registered paths and require a published WordPress page with matching `_tio2_page_id` and `_tio2_site_scope=tio2-my` before returning a URL.

`tio2_public_base_url()` reads `TIO2_PUBLIC_URL`, validates one HTTPS origin without credentials/path/query/fragment, and returns a trailing slash. `compose.yaml` defines `TIO2_PUBLIC_URL` from the environment with `https://tio2products.com` as the local default; it remains separate from WordPress `home`.

- [ ] **Step 4: Implement idempotent migration and recovery**

On `init` priority 1, install `tio2_products_content` only when absent and create one published `products` Page with template `page-products.php`, `_tio2_page_id=PRODUCT-000`, `_tio2_site_scope=tio2-my`, and `_tio2_managed_page=1`. Refuse to take over a pre-existing conflicting slug. Persist the exact pre-migration state before writes. `scripts/products-migration.php` supports `status`, `migrate`, `rollback`, and `resume`; rollback affects only data created by this migration and suspends automatic remigration until resume.

- [ ] **Step 5: Verify migration against a real isolated WordPress database**

`tests/products-migration.py` records homepage content hash, triggers migration twice, edits one Products field, reruns migration, asserts both homepage and Products edits persist, performs rollback, asserts homepage still returns 200 and `/products/` returns 404, resumes, and asserts `/products/` returns 200 with defaults. The test restores the exact pre-test Products content/page state in `finally`.

Run: `python tests/products-migration.py`

Expected: PASS with idempotent, edit-preserving, rollback, and resume checks.

- [ ] **Step 6: Commit**

```powershell
git add compose.yaml scripts/products-migration.php tests/php/runtime-config-test.php tests/php/products-model-test.php tests/products-migration.py wp-content/plugins/tio2-content wp-content/themes/tio2-malaysia/inc/seo.php
git diff --cached --check
git commit -m "feat: add products content migration and site identity"
```

### Task 2: Server-rendered Products Page, Readiness, and Machine Metadata

**Files:**
- Create: `wp-content/themes/tio2-malaysia/page-products.php`
- Create: `wp-content/themes/tio2-malaysia/template-parts/root-page-hero.php`
- Create: `tests/products-http.py`
- Create: `tests/php/products-route-test.php`
- Modify: `wp-content/themes/tio2-malaysia/functions.php`
- Modify: `wp-content/themes/tio2-malaysia/header.php`
- Modify: `wp-content/themes/tio2-malaysia/inc/seo.php`

**Interfaces:**
- Consumes: Task 1 product fields, managed page, public URL helper, route registry/resolver.
- Produces: `/products/` HTTP 200, exact approved module order, ready-only actions, shared RootPageHero, Products current state, CollectionPage/BreadcrumbList/14-item ItemList/FAQPage JSON-LD.

- [ ] **Step 1: Write failing HTTP and route-state tests**

`products-http.py` requests `/` and `/products/`, parses real HTML, and asserts both pages use `https://tio2products.com` for canonical/OG/Schema while being fetched from `127.0.0.1`. For Products it asserts one exact H1, exact Title/Meta, module order, fourteen unique rows and summaries, 6/5/2/1 groups, five steps, five FAQs in initial DOM, clean RFQ URLs, no governance fields, no ecommerce claims, and zero external target actions in the initial all-unready state.

`products-route-test.php` creates real temporary Malaysia-scoped pages with approved Page IDs/paths, verifies zero/partial/full resolver states, then creates wrong-scope and wrong-ID records and proves they fail closed. Cleanup runs in `finally`.

- [ ] **Step 2: Run tests and confirm RED**

Run:

```powershell
python tests/products-http.py
docker compose exec -T wordpress php /workspace/tests/php/products-route-test.php
```

Expected: `/products/` is not yet implemented and route/Schema helpers are missing.

- [ ] **Step 3: Implement shared navigation/current state and page template**

Make `tio2_navigation()` derive current route identity so Home is current only on `/` and Products only on `PRODUCT-000`, on both desktop and dialog surfaces. Render the approved breadcrumb, shared RootPageHero, selector default, Process parent plus CR-901 row, fourteen-row directory, five evaluation steps, conditional Support module, five FAQ disclosures, final RFQ, and the existing shared Footer/Cookie UI. Grade actions are generated only by `tio2_resolve_route()` and remain programmatically associated with the grade name.

- [ ] **Step 4: Implement page-aware SEO/Schema**

Keep homepage graph behavior while moving every absolute URL to `tio2_public_base_url()`. Products emits exact approved metadata, no social image, noindex in local/pre-release, and JSON-LD containing CollectionPage, BreadcrumbList, ItemList with fourteen ordered identities, minimal Product descriptions/categories, FAQPage, and shared site/brand references. Unready products omit URL and `@id`; ready products use only resolved Malaysia URLs.

- [ ] **Step 5: Verify GREEN and homepage regression**

Run:

```powershell
python tests/products-http.py
python tests/http-contract.py
docker compose exec -T wordpress php /workspace/tests/php/products-route-test.php
```

Expected: both page contracts pass; homepage content remains unchanged apart from the approved public-origin migration.

- [ ] **Step 6: Commit**

```powershell
git add tests/products-http.py tests/php/products-route-test.php wp-content/themes/tio2-malaysia
git diff --cached --check
git commit -m "feat: render the products hub and scoped metadata"
```

### Task 3: Selector, FAQ, and Responsive Visual Contract

**Files:**
- Create: `wp-content/themes/tio2-malaysia/assets/products.css`
- Create: `wp-content/themes/tio2-malaysia/assets/products.js`
- Create: `tests/products-browser.spec.mjs`
- Modify: `wp-content/themes/tio2-malaysia/functions.php`

**Interfaces:**
- Consumes: server-rendered Product DOM and a JSON data element containing display labels, relation sets, and ready-only URLs.
- Produces: keyboard-safe selector and FAQ behavior; approved 1440/1024/768/390 responsive geometry; no-JS readable FAQ; scoped screenshots/metrics.

- [ ] **Step 1: Write failing Playwright tests**

For 1440/1024/768/390, assert no horizontal overflow, exact module order, expected Header height, complete long-copy wrapping, visible active navigation, axe WCAG A/AA/2.1AA zero violations, and screenshots under `PRODUCT_EVIDENCE_DIR`. At 390 assert H1 `36px`, weight `700`, letter spacing `normal` or `0px`, natural four-line layout, and every visible control/link at least 44×44.

Add interaction tests for all six selector counts, Not Sure, focus retention, live heading, no URL change, no links for unready grades, FAQ 1+4 initial visual state with all answers in server HTML, Enter/Space toggles, Mobile Menu trap/Escape/return/current state, Cookie dialog isolation, and reduced-motion behavior.

- [ ] **Step 2: Run tests and confirm RED**

Run: `npx playwright test tests/products-browser.spec.mjs`

Expected: failure because product assets and interactions are absent.

- [ ] **Step 3: Implement scoped CSS from the frozen visual result**

Translate the approved Gate4 CSS to `.products-page`/`.product-*` selectors while reusing existing design tokens and shared Header/Footer. Load `products.css` only for `PRODUCT-000`. Avoid fixed-height clipping; preserve two-column desktop directory, tablet reflow, one-column mobile, and 44px controls.

- [ ] **Step 4: Implement safe interactions**

Read the JSON data element, build result nodes with `textContent`/DOM APIs, and create actions only when a ready URL is present. Default Coatings renders but an `explicitSelection` flag remains false until user activation; RFQ hrefs remain clean. FAQ code only toggles `hidden`, `aria-expanded`, and its decorative sign; server HTML retains all answers.

- [ ] **Step 5: Verify GREEN**

Run: `npx playwright test tests/products-browser.spec.mjs`

Expected: all viewport, selector, FAQ, menu, cookie, focus, axe, and screenshot checks pass.

- [ ] **Step 6: Commit**

```powershell
git add tests/products-browser.spec.mjs wp-content/themes/tio2-malaysia/assets/products.css wp-content/themes/tio2-malaysia/assets/products.js wp-content/themes/tio2-malaysia/functions.php
git diff --cached --check
git commit -m "feat: add responsive products interactions"
```

### Task 4: Products CMS Editing and Stateful Readiness Evidence

**Files:**
- Create: `tests/products-editor.spec.mjs`
- Create: `tests/products-readiness.py`
- Modify: `wp-content/plugins/tio2-content/includes/admin.php`
- Modify: `wp-content/plugins/tio2-content/assets/admin.js`

**Interfaces:**
- Consumes: Task 1 product schema/content and Task 2 resolver.
- Produces: authenticated Products editor/preview, separate optimistic revision token, exact restore, real zero/partial/full route-state evidence.

- [ ] **Step 1: Write failing editor tests**

Log into the isolated site, open `Site content → Products`, assert a distinguishable Products heading and `/products/` preview, edit one visible summary and SEO description, save, verify visible HTML and JSON-LD use the same edited string, reject invalid path/incomplete fields with 422, reject stale revisions with 409, then restore the exact original option in `finally`. Verify unauthenticated and missing-nonce writes do not change data.

- [ ] **Step 2: Run editor test and confirm RED**

Run: `npx playwright test tests/products-editor.spec.mjs`

Expected: failure because the Products editor endpoint is absent.

- [ ] **Step 3: Implement isolated Products admin form**

Add a submenu under the existing Site content menu, render grouped typed fields from `products-schema.json`, use `manage_options`, a Products-specific nonce/action/revision, validate before update, preserve the homepage form/action, and link preview to `/products/`.

- [ ] **Step 4: Implement real readiness-state test**

`products-readiness.py` uses a WP-CLI fixture that creates and later trashes only test-owned pages with matching Page ID/scope metadata. It captures zero/partial/full visible action counts, Process 2/1/0, Support 3/2/1/0, CR-901 independent gating, fourteen ItemList identities/order, ready-only Schema URLs, wrong-scope fail-closed behavior, and exact cleanup/restoration.

- [ ] **Step 5: Verify GREEN**

Run:

```powershell
npx playwright test tests/products-editor.spec.mjs
python tests/products-readiness.py
python tests/products-http.py
```

Expected: editor and real state matrix pass; content and temporary target state restore exactly.

- [ ] **Step 6: Commit**

```powershell
git add tests/products-editor.spec.mjs tests/products-readiness.py wp-content/plugins/tio2-content/includes/admin.php wp-content/plugins/tio2-content/assets/admin.js
git diff --cached --check
git commit -m "feat: add products CMS editing and readiness validation"
```

### Task 5: Gate8 Candidate, Evidence, and Handoff

**Files:**
- Create: `scripts/products-evidence.py`
- Create: `docs/verification/products/*`
- Create: `docs/handoffs/PRODUCT-000-gate8.md`
- Create ignored: `.runtime/handoff/products/gate8_evidence_manifest.json`
- Modify: `README.md`
- Modify: `CONTRIBUTING.md`

**Interfaces:**
- Consumes: committed Tasks 1–4, exact Gate6 acceptance IDs, isolated runtime, Gate8 Evidence Manifest Schema V1.0.
- Produces: implementation commit, evidence commit, `wp-*` artifact identity, exact Products content hash, receipt/evidence hashes, machine-valid manifest, held runtime.

- [ ] **Step 1: Add evidence generator and failing contract test**

The generator refuses a dirty implementation tree, records the implementation commit, hashes the actual theme/plugin artifact, snapshots both homepage and Products options, captures environment/route/dependency state, and builds a manifest whose receipt references equal the complete evidence path set. Add a test that runs it against a deliberately incomplete temporary evidence directory and expects a non-zero validation result.

- [ ] **Step 2: Run the complete non-destructive and isolated-data suites**

Run PHP syntax over every theme/plugin PHP file, both PHP contract suites, homepage and Products HTTP tests, Products browser/editor/readiness/migration tests, existing homepage editor/browser tests redirected to ignored output, identity, and negative runtime checks. Every modifying test uses the dedicated `d32-product-000` data environment and restores in `finally`.

- [ ] **Step 3: Capture final Products evidence once**

Set `PRODUCT_EVIDENCE_DIR=docs/verification/products` and capture 1440/1024/768/390 screenshots, layout/axe JSON, selector/FAQ/menu/cookie states, initial HTML, SEO/Schema parse, migration/edit/restore results, route-state matrix, dependencies, environment, source hashes, artifact identity, content snapshots, test results, and acceptance mapping. Do not rewrite homepage evidence.

- [ ] **Step 4: Commit the implementation candidate and evidence**

First commit any final code/test correction as its own verified work item. Then write the Gate8 receipt with one `EVIDENCE: <repo-relative-path>` line per evidence file, commit the receipt/evidence, and record exact implementation/evidence commits.

- [ ] **Step 5: Generate and validate the final manifest**

Generate `.runtime/handoff/products/gate8_evidence_manifest.json` after the evidence commit, validate it with D23's original `validate_evidence_manifest.py`, then run `gate9_preflight.py --rounds 2`. `require_build_marker=false` is allowed only with the exact WordPress artifact/content markers in runtime checks.

- [ ] **Step 6: Hold runtime and report boundaries**

Keep `http://127.0.0.1:8232/products/` on the exact candidate until `GATE9_PASS_OR_RETURN_NOTICE`. Report page quality separately from integration (external Grade/Process/Support/RFQ targets) and release (Gate10/public deployment); do not claim receiver readiness or deployment.
