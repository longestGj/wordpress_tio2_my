# CONV-RFQ D32 Gate 8 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved English `/request-a-quote/` WordPress page, editable content, strict form validation, fail-closed receiver adapter, responsive interaction, and machine-verifiable Gate 8 evidence without making any real submission.

**Architecture:** Preserve the accepted homepage `tio2_content` option and shared Chrome. Add an independent typed `tio2_rfq_content` option and idempotent managed-page migration, a same-origin WordPress submission controller with injectable receiver transport, and an RFQ-only template/CSS/JavaScript layer. The Gate 8 runtime uses only a deterministic local fake receiver; any production adapter remains disabled unless explicit production-only configuration is complete.

**Tech Stack:** WordPress 7.1, PHP 8.3, MariaDB 11.4, native CSS/JavaScript, Python 3 + BeautifulSoup, Playwright + axe-core, Docker Compose.

**Spec:** `D:/23MySec/pages/conversion/request-a-quote/06_handoff/CONV-RFQ_D32_GATE6_HANDOFF_PACKAGE_V0.2.md` (SHA-256 `5a19e738be8dd741ff533bcfe4951d23710f3c3a7411ba92c74211bbfac09a9d`), closed by `CONV-RFQ-D32-G6-CLOSE-01` and incorporating the exact Gate 2, Gate 5, and Gate 7 contracts it identifies.

## Global Constraints

- Scope is only `CONV-RFQ`, English `/request-a-quote/`, `site_scope=tio2-my`; do not implement Privacy, Sample, Documents, CMP, Grade, Application, Product, or Market owner pages.
- Base is the latest clean `develop` commit `ce4147c7076112934b3dc6d8d97e983efb045f2f`; worktree is `D:/32Wordpress_new/.worktrees/conv-rfq-gate8`, branch `codex/conv-rfq-gate8`.
- Every runtime command uses `COMPOSE_PROJECT_NAME=d32-conv-rfq-gate8`, `TIO2_LOCAL_PORT=8242`, `TIO2_LOCAL_URL=http://127.0.0.1:8242`, and the project-owned volumes created under that Compose project.
- Never submit to Web3Forms or any email receiver. All Gate 8 receiver tests use injected transports or the deterministic local fake, and no approved recipient or secret may enter source, HTML, JavaScript, logs, screenshots, or evidence.
- Success requires an HTTP 2xx receiver response whose parsed payload contains literal `success === true`. Status, message text, or transport success alone never confirms receipt.
- Registered field errors map only to known fields and preserve all values; configured unavailability maps to `service_unavailable`; false, empty, malformed, ambiguous, non-2xx, timeout, parse, and network results map to retryable `submission_unconfirmed`.
- Render exactly eleven editable controls plus fixed `quantity_unit=MT`; Destination Country stays free text with placeholder `Enter the destination country`.
- Internal `page_id`, `site_scope`, attribution keys, receiver state/configuration, and governance identifiers must not enter public URL, HTML, DOM, accessible names, analytics, metadata, or Schema.
- Privacy, Sample, and Documents links are always visible and exact even while their destinations remain external dependencies.
- Preserve homepage content and shared Header/Footer/Menu/Cookie Settings. The selected `develop` baseline does not contain the separate Products feature branch, so same-branch Products regression is reported `NOT_TESTED / integration dependency`, never PASS.
- The formal public origin is `https://tio2products.com/`; local request URLs never become canonical, Open Graph, or JSON-LD identity.
- Development and Gate 8 remain `noindex, nofollow`. Gate 10, push, PR, merge, deploy, production CMS writes, publication, DNS, sitemap, and indexing are not authorized.
- Evidence writes only to `docs/verification/request-a-quote/` or ignored `.runtime/`; do not overwrite Home or Products evidence.

## Review Focus

- Duplicate or delayed submission responses must not turn an earlier failed attempt into a success state or issue a second receiver call.
- Unicode length boundaries, decimal edge values, international `+`, long URLs, and hostile markup must validate without truncation, injection, or lost values.
- Missing/wrong scope, conflicting slugs, stale CMS revisions, rollback, and resumed migration must fail closed without altering homepage content.
- Anonymous POSTs with missing/invalid nonce, wrong Origin, honeypot content, oversized bodies, unexpected keys, or exceeded rate limits must make zero receiver calls and disclose no values.
- Query parameters containing broad regions, internal IDs, unsupported values, arrays where scalars are expected, or buyer data must neither prefill nor alter canonical/metadata/Schema.

---

### Task 1: Typed RFQ Content and Managed-Page Migration

**Files:**
- Create: `wp-content/plugins/tio2-content/includes/rfq.php`
- Create: `wp-content/plugins/tio2-content/rfq-schema.json`
- Create: `wp-content/plugins/tio2-content/rfq-defaults.json`
- Create: `scripts/rfq-migration.php`
- Create: `tests/php/rfq-model-test.php`
- Create: `tests/rfq-migration.py`
- Modify: `wp-content/plugins/tio2-content/tio2-content.php`
- Modify: `scripts/snapshot.php`

**Interfaces:**
- Consumes: `tio2_validate_content()`, `tio2_site_ready()`, WordPress options/pages/meta, approved copy and field contract.
- Produces: `tio2_rfq_schema(): array`, `tio2_rfq_content(): array`, `tio2_rfq_field(string): string`, `tio2_rfq_form_contract(): array`, managed page metadata `_tio2_page_id=CONV-RFQ`, migration status/rollback/resume commands.

- [ ] **Step 1: Write the failing model test**

Create a pure PHP test with literal expected values for the exact 11 controls, fixed `MT`, requiredness, 14+unknown grade order, 6+other application order, length limits, helpers, 15 error strings, page/state copy, routes, and SEO fields. A wrong option order, mutable unit control, missing placeholder, or missing string must fail.

- [ ] **Step 2: Run RED**

Run: `docker compose exec -T wordpress php /workspace/tests/php/rfq-model-test.php`

Expected: non-zero because the RFQ model and JSON records do not exist.

- [ ] **Step 3: Implement the isolated RFQ record**

Use a flat schema/default pair under `tio2_rfq_content` with `site_scope=tio2-my` and schema version 1. Keep every Buyer Clean string, label, option label, helper, error, state message, route, and SEO value editable; keep stable field keys and validation rules in PHP. Validate exact schema key equality without comparing editor values to historical approval text at runtime.

- [ ] **Step 4: Write and run the migration RED test**

The test records the homepage content hash, migrates twice, edits one RFQ field, migrates again, rolls back, resumes, and restores its exact starting state. It must fail before the managed-page migration exists.

Run: `python tests/rfq-migration.py`

Expected: non-zero because no RFQ migration/page exists.

- [ ] **Step 5: Implement idempotent migration and recovery**

Create a single published `request-a-quote` Page with `page-request-a-quote.php`, `_tio2_page_id=CONV-RFQ`, `_tio2_site_scope=tio2-my`, and `_tio2_managed_page=1`. Refuse a conflicting slug; preserve pre-existing valid content; snapshot before writes; rollback only migration-owned RFQ state; require explicit resume after rollback; never modify `tio2_content`.

- [ ] **Step 6: Verify GREEN and commit**

Run:

```powershell
docker compose exec -T wordpress php /workspace/tests/php/rfq-model-test.php
python tests/rfq-migration.py
python tests/http-contract.py
```

Expected: RFQ model/migration pass and homepage remains unchanged.

Commit: `feat: add RFQ content migration and model`

### Task 2: Server Validation, Security, and Receiver Adapter

**Files:**
- Create: `wp-content/plugins/tio2-content/includes/rfq-submission.php`
- Create: `tests/php/rfq-submission-test.php`
- Create: `tests/rfq-endpoint.py`
- Modify: `wp-content/plugins/tio2-content/tio2-content.php`
- Modify: `compose.yaml`

**Interfaces:**
- Consumes: Task 1 field/content contract; WordPress AJAX, nonce, transient, HTTP, and environment APIs.
- Produces: `tio2_rfq_validate_submission(array): array|WP_Error`, `tio2_rfq_classify_receiver_result(array): array`, `tio2_rfq_receiver_adapter(array, ?callable): array`, and anonymous same-origin `tio2_rfq_submit` JSON endpoint.

- [ ] **Step 1: Write receiver classification and validation tests**

Use hand-built fixtures for valid input; all 15 validation conditions; unknown keys; fixed-unit tampering; registered versus unknown provider field errors; explicit true; false; message-only 2xx; empty/malformed/ambiguous 2xx; non-2xx; timeout; network/parse exception; and configured unavailable. Count injected transport calls and assert no automatic retry.

- [ ] **Step 2: Run RED**

Run: `docker compose exec -T wordpress php /workspace/tests/php/rfq-submission-test.php`

Expected: non-zero because validation/classification/adapter functions do not exist.

- [ ] **Step 3: Implement minimal server contract**

Normalize scalars, trim Unicode text, preserve `+`, require registered enum values and positive finite decimal quantity, validate email and absolute HTTP(S) website, reject unknown fields, and return only registered field messages. Add internal `quantity_unit=MT`, workflow, locale, scope, and page identity only after validation.

The adapter accepts an injected callable for tests. The production path remains fail-closed unless explicit production-only mode, enable flag, and access key are configured; local Gate 8 uses deterministic fake outcomes and never opens a network connection.

- [ ] **Step 4: Write endpoint security RED tests**

POST to the real isolated WordPress endpoint and prove method, scope, nonce, exact Origin, 32 KiB body ceiling, honeypot, expected-key allowlist, and rate limit are enforced before the fake receiver. Assert public JSON and server logs contain no submitted email, phone, website, free text, secret, recipient, or full receiver payload.

Run: `python tests/rfq-endpoint.py`

Expected: non-zero until the endpoint and local fixture controls exist.

- [ ] **Step 5: Implement endpoint and verify GREEN**

Return a compact public envelope containing only `state`, registered `fieldErrors`, and approved state text keys. Do not echo values or provider messages. Use privacy-safe HMAC rate-limit keys and no value logging.

Run:

```powershell
docker compose exec -T wordpress php /workspace/tests/php/rfq-submission-test.php
python tests/rfq-endpoint.py
```

Expected: every deterministic branch passes with zero external network/email traffic.

Commit: `feat: add fail-closed RFQ receiver controller`

### Task 3: Server-Rendered RFQ Page, Prefill, and Metadata

**Files:**
- Create: `wp-content/themes/tio2-malaysia/page-request-a-quote.php`
- Create: `tests/rfq-http.py`
- Create: `tests/php/rfq-route-test.php`
- Modify: `wp-content/themes/tio2-malaysia/functions.php`
- Modify: `wp-content/themes/tio2-malaysia/inc/seo.php`

**Interfaces:**
- Consumes: Task 1 RFQ content/page and Task 2 endpoint contract.
- Produces: `/request-a-quote/` HTTP 200; exact module/field DOM; neutral safe prefill; current page identity without visible `CURRENT`; exact canonical/OG/Schema.

- [ ] **Step 1: Write failing route and HTTP tests**

Assert a managed Malaysia page is the only valid route owner. Parse real HTML for one H1, module order, 11 controls plus fixed MT text, option order, requiredness, placeholder, helpers, no first-load errors, exact three external links, no internal identifiers, no buyer-data leakage, no CAPTCHA/remarketing/analytics, and the clean formal origin.

Run:

```powershell
docker compose exec -T wordpress php /workspace/tests/php/rfq-route-test.php
python tests/rfq-http.py
```

Expected: failure because the page template and page-aware metadata do not exist.

- [ ] **Step 2: Implement page identity and semantic template**

Render shared Header/Footer/Menu/Cookie Settings, breadcrumb, Hero, one form surface, three field groups, privacy notice, only one solid page-body CTA, and Other Request Types. Build controls from the server contract, use contextual escaping, and never output `CONV-RFQ`, scope, attribution, receiver mode, or fake outcome.

- [ ] **Step 3: Implement fail-closed prefill**

Allow exact registered `grade_id` and `application_id`, an explicit actual destination country up to 100 characters, and approved neutral public context only. Silently discard arrays-for-scalars, stale/unsupported values, broad regions, internal IDs, and overlength text. For M-2377, allow neutral `Sulfate` context without inferring a grade/application relationship. Prefill remains editable and creates no first-load error.

- [ ] **Step 4: Implement RFQ metadata**

Emit exact Title, Meta, language, canonical, OG URL/title/description, `noindex, nofollow`, `WebPage`, and `BreadcrumbList`. Reuse stable WebSite/Organization references only; omit Product, Offer, FAQ, ContactPage, buyer values, local URLs, and governance data. Keep homepage graph behavior unchanged.

- [ ] **Step 5: Verify GREEN and commit**

Run:

```powershell
docker compose exec -T wordpress php /workspace/tests/php/rfq-route-test.php
python tests/rfq-http.py
python tests/http-contract.py
```

Expected: RFQ and homepage HTTP contracts pass.

Commit: `feat: render scoped RFQ page and metadata`

### Task 4: Client State Machine and Responsive Visual Contract

**Files:**
- Create: `wp-content/themes/tio2-malaysia/assets/rfq.css`
- Create: `wp-content/themes/tio2-malaysia/assets/rfq.js`
- Create: `tests/rfq-browser.spec.mjs`
- Modify: `wp-content/themes/tio2-malaysia/functions.php`

**Interfaces:**
- Consumes: Task 2 public response envelope and Task 3 semantic form DOM.
- Produces: client validation, focus/error summary, pending duplicate prevention, retry/value retention, unavailable/success states, responsive Gate 5 visual implementation.

- [ ] **Step 1: Write failing browser tests**

At 1440/1280/1024/768/430/390/375/320 assert semantic order, no overflow/crop, desktop-only two-column field grid, tablet/mobile one column, quantity/MT adjacency, 44 px targets, 200% browser zoom, visible focus, reduced motion, and axe A/AA zero serious/critical violations. Capture initial, validation, pending, failure, unavailable, success, mobile menu, and long-value states.

- [ ] **Step 2: Run RED**

Run: `npx playwright test tests/rfq-browser.spec.mjs`

Expected: failure because RFQ assets and client behavior are absent.

- [ ] **Step 3: Implement scoped Gate 5 styling**

Use existing tokens and shared Chrome. Add the single centred flow, white form surface, 12 px radius, subtle Navy shadow, thin Teal leading rule, form-only two-column desktop grid, and exact responsive collapse without fixed-height clipping or page-specific imagery.

- [ ] **Step 4: Implement client validation and submission state**

Use native DOM APIs and `textContent`; validate against the same public limits before POST; focus the summary after invalid submit; associate field errors with `aria-invalid` and `aria-describedby`; disable only the form submit while pending; ignore duplicate activation; retain values for every non-success result; clear buyer values only after explicit `receipt_confirmed`; restore retry action and focus; never disable shared RFQ navigation.

- [ ] **Step 5: Verify GREEN and commit**

Run:

```powershell
npx playwright test tests/rfq-browser.spec.mjs
npx playwright test tests/browser.spec.mjs
```

Expected: RFQ viewport/state/a11y tests and shared Home Chrome tests pass.

Commit: `feat: add accessible RFQ interactions and layout`

### Task 5: RFQ CMS Editing, Scope Isolation, and Recovery Regression

**Files:**
- Create: `tests/rfq-editor.spec.mjs`
- Create: `tests/rfq-isolation.py`
- Modify: `wp-content/plugins/tio2-content/includes/admin.php`
- Modify: `wp-content/plugins/tio2-content/assets/admin.js`
- Modify: `tests/identity.py`
- Modify: `tests/negative-runtime.py`

**Interfaces:**
- Consumes: Task 1 record/migration and Tasks 2–4 public behavior.
- Produces: RFQ submenu/editor with independent revision token, exact restore, wrong/missing-scope fail-closed evidence, Home/shared Chrome regression.

- [ ] **Step 1: Write failing editor and isolation tests**

The editor test changes visible Hero/form/state/SEO text, proves frontend and metadata update from the same record, rejects incomplete/invalid content, missing nonce, unauthorized requests, and stale revisions, then restores exact content in `finally`. Isolation tests exercise correct/wrong/missing scope and warm/cold request paths while preserving homepage content.

- [ ] **Step 2: Run RED**

Run:

```powershell
npx playwright test tests/rfq-editor.spec.mjs
python tests/rfq-isolation.py
```

Expected: failure because no RFQ admin endpoint exists and isolation helpers are incomplete.

- [ ] **Step 3: Implement RFQ editor and recovery hooks**

Add a submenu under Site content with `manage_options`, RFQ-specific nonce/action/revision, grouped typed fields, separate preview, strict validation before update, and no modification of the homepage save path. Extend snapshot/identity/negative helpers only for the RFQ-owned record and evidence directory.

- [ ] **Step 4: Verify complete feature regression**

Run PHP lint over every theme/plugin PHP file, Task 1–3 PHP tests, RFQ HTTP/endpoint/migration/isolation, RFQ browser/editor, existing homepage HTTP/browser/editor, identity, and negative-runtime tests. Record `/products/` as baseline-unimplemented on this exact branch rather than importing or claiming the separate Products candidate.

- [ ] **Step 5: Commit**

Expected: all implemented-branch tests pass; every modifying test restores exact pre-test data.

Commit: `feat: add RFQ CMS editing and isolation checks`

### Task 6: Gate 8 Evidence, Artifact, and Machine Handoff

**Files:**
- Create: `scripts/rfq-evidence.py`
- Create: `tests/rfq-evidence.py`
- Create: `docs/verification/request-a-quote/*`
- Create: `docs/handoffs/CONV-RFQ-gate8.md`
- Create ignored: `.runtime/handoff/request-a-quote/gate8_evidence_manifest.json`
- Modify: `scripts/artifact.py`
- Modify: `README.md`
- Modify: `CONTRIBUTING.md`

**Interfaces:**
- Consumes: committed Tasks 1–5, the exact 15 acceptance IDs, six dependency IDs, the Gate8→9 schema, isolated runtime/data, and actual theme/plugin bytes.
- Produces: exact implementation/evidence/observed commits, `wp-*` build identity, RFQ and Home content identities, evidence hashes, AC/DEP mapping, machine-valid manifest, and held Gate 9 runtime.

- [ ] **Step 1: Write failing evidence-contract test**

Run the generator against an incomplete temporary directory and require a non-zero result. Assert the final mapping contains exactly all 15 `RFQ-D32-AC-*` and six `RFQ-D32-DEP-*`, each with PASS/FAIL/NOT_TESTED status, evidence, commands, result, owner/boundary, and manifest `proves` coverage.

- [ ] **Step 2: Run the full final suite once**

Capture command, exit code, duration, and concise output for every required PHP, HTTP, browser/editor, migration, endpoint, scope, identity, negative-runtime, secret/PII scan, and Home regression check. Use deterministic fake receiver only and verify its network request count is zero outside the same-origin WordPress endpoint.

- [ ] **Step 3: Capture final evidence**

Record HTML/head/JSON-LD, screenshots at 1440/768/390 plus required extra widths, initial/validation/pending/failure/unavailable/mock-success states, CMS edit/restore, migration/rollback/resume, scope matrix, receiver branch matrix, security negatives, route/dependency readiness, environment, source hashes, artifact identity, and test results. Never store buyer fixture values, credentials, approved recipient, or secret.

- [ ] **Step 4: Commit implementation then evidence**

Commit any final code/test correction as its own verified work item. Then commit only the receipt and evidence, and record exact implementation/evidence/observed identities and clean/dirty status.

- [ ] **Step 5: Generate and validate the final manifest**

Run D23's original `validate_evidence_manifest.py` and `gate9_preflight.py --rounds 2`. Verify receipt `EVIDENCE:` lines equal the manifest evidence set and every hash matches the committed working-tree byte stream.

- [ ] **Step 6: Hold and hand off**

Keep `http://127.0.0.1:8242/request-a-quote/` and its isolated data unchanged with hold `GATE9_PASS_OR_RETURN_NOTICE`. Report all six external dependencies open, production receiver root open, no real submission performed, Products same-branch regression `NOT_TESTED`, and Gate 10/release not authorized. Send the exact candidate to D23 Gate 9 and stop feature work.

Commit: `docs: deliver CONV-RFQ Gate 8 evidence`
