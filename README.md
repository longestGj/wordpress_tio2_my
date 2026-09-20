# TiO₂ Malaysia — WordPress site

HOME-001, the PRODUCT-000 `/products/` Hub and CONV-RFQ use one custom PHP theme and a first-party typed-content plugin. The approved designs are fixed; page copy, registered local paths, form options, hero media, grades and SEO are editable in WordPress **Site content**. Home, Products and RFQ use independent typed content records and revision tokens, with no approval-state runtime dependency.

开发、分支、提交、验证与验收约定见 [开发流程](CONTRIBUTING.md)。发布路径是：本地 develop 集成 → 本地集成测试 → 本地 main 快进到同一提交 → 推送 origin/main → 可复用 CI → 不可变镜像 → 自动生产部署。
开发代理执行本仓库任务时，先阅读 [AGENTS.md](AGENTS.md)。
所有新工作分支从最新本地 develop 创建并合回本地 develop。功能分支和 develop 不常规推送远端；只在精确候选通过集成后更新并正常推送 main，绝不强推或绕过自动质量门禁。

## Local preview

Requires Docker Desktop, PowerShell, Python with BeautifulSoup, Node and Chrome for browser tests. For the already-installed candidate, run `./scripts/local.ps1 -Action Start` and `npm ci`; do not bootstrap it again.

For a **fresh installation with new empty volumes only**, start the containers and wait for WordPress files to initialize. The install command prompts for the local `.env` file's `D32_ADMIN_PASSWORD` value; it is not printed by these commands.

```powershell
./scripts/local.ps1 -Action Start
docker compose run --rm wpcli core install --url=http://127.0.0.1:8232 --title="TiO2 Malaysia" --admin_user=d32editor --admin_email=editor@example.test --skip-email --prompt=admin_password
docker compose run --rm wpcli theme activate tio2-malaysia
docker compose run --rm wpcli eval-file /workspace/scripts/bootstrap.php
New-Item -ItemType Directory -Force .runtime | Out-Null
New-Item -ItemType File .runtime/bootstrap-complete | Out-Null
npm ci
```

Check each command succeeds before continuing. Open http://127.0.0.1:8232/, `/products/`, `/request-a-quote/` and `/wp-admin/`. Local administrator: `d32editor`; its generated password is the `D32_ADMIN_PASSWORD` value in the ignored local `.env` file. Do not share or commit that file. Bootstrap refuses to rerun once `.runtime/bootstrap-complete` exists. The Products and RFQ migrations run idempotently after plugin activation and preserve existing edits; Products also provides explicit `status`, `rollback` and `resume` actions through `scripts/products-migration.php`.

## Verification and recovery

```powershell
npx playwright test
python tests/http-contract.py
python tests/products-http.py
npx playwright test tests/products-browser.spec.mjs tests/products-editor.spec.mjs
python tests/products-readiness.py
python tests/products-migration.py
python tests/identity.py
python tests/negative-runtime.py
docker compose run --rm wpcli eval-file /workspace/scripts/snapshot.php restore /workspace/docs/verification/home/content-restored.json
```

Run data-modifying Products tests only in an isolated runtime. The scripts accept the dedicated local `d32-product-000` environment and the dynamic `tio2-ci-*` environments created by `scripts/ci-environment.sh`; they reject shared or remote runtimes. Editor, readiness and migration tests restore their content and test-owned routes in `finally`. Homepage editor/negative tests can be redirected with `HOME_EVIDENCE_DIR=.runtime/home-regression` so historical `docs/verification/home/` evidence is not overwritten.

Products recovery examples:

```powershell
docker compose run --rm wpcli eval-file /workspace/scripts/products-migration.php status
docker compose run --rm wpcli eval-file /workspace/scripts/products-migration.php rollback
docker compose run --rm wpcli eval-file /workspace/scripts/products-migration.php resume
```

The CONV-RFQ final suite must run only in its isolated Compose project with the deterministic fake receiver; it deliberately exercises data-changing migration, editor and scope-failure paths and restores the original records:

```powershell
$env:COMPOSE_PROJECT_NAME='d32-conv-rfq-gate8'
$env:TEST_BASE_URL='http://127.0.0.1:8242'
$env:TIO2_LOCAL_URL='http://127.0.0.1:8242'
$env:TIO2_RFQ_RECEIVER_MODE='fake'
python scripts/rfq-evidence.py --run-suite
python scripts/artifact.py --evidence-dir docs/verification/request-a-quote
python scripts/rfq-evidence.py --generate
python scripts/rfq-evidence.py --validate-dir docs/verification/request-a-quote
```

Editor tests temporarily change content and restore it in `finally`. Negative runtime tests deliberately produce local 503 responses and restore the same snapshot; run only on this isolated preview. The snapshot restores editable content, not the entire database. It references the existing site-owned hero attachment; keep the `wp_data` and `db_data` volumes. For a fresh installation use the seed/bootstrap, which imports `content/media/hero.png`; do not copy old attachment IDs blindly.

To recover code, use the implementation commit recorded in `docs/verification/home/artifact.json` or its ignored `.runtime/artifacts/<build_id>` copy. Restart only this project's WordPress container to clear PHP opcode cache, then rerun identity and HTTP checks. `scripts/artifact.py` packages committed theme/plugin bytes and verifies their hashes; it does not deploy anything.

The local environment is noindex and binds only loopback. HOME-001, PRODUCT-000, CONV-RFQ and the shared navigation/footer/menu/cookie UI are implemented. PRODUCT-000 intentionally does not create its fourteen Grade pages, two Process pages, Applications, Documents or Markets; those routes remain genuine external 404 dependencies and Hub actions fail closed except for always-visible clean RFQ links. Privacy, Sample/Documents delivery, child Product routes, the real RFQ receiver, CMP, analytics and indexing are not ready. The production RFQ adapter stays disabled and fail-closed unless production-only configuration is supplied. A normal `origin/main` push runs the complete reusable CI suite before it can build an immutable ARM64 image and deploy. Production remains `noindex, nofollow` until the complete site is ready and indexing is explicitly authorized.

See `docs/handoffs/HOME-001-gate8.md`, `docs/handoffs/PRODUCT-000-gate8.md` and `docs/handoffs/CONV-RFQ-gate8.md` for the historical candidate handoffs. Later independent acceptance and release records supplement those fixed historical receipts.
