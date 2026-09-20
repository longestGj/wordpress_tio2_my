# TiO₂ Malaysia — WordPress site

HOME-001 and the PRODUCT-000 `/products/` Hub use one custom PHP theme and a first-party typed-content plugin. The approved designs are fixed; page copy, registered local paths and SEO are editable in WordPress **Site content**. Homepage and Products content use separate typed options and revision tokens.

开发、分支、提交、验证与验收约定见 [开发流程](CONTRIBUTING.md)。GitHub CI、`develop → main` 发布流和 main 自动生产部署已经建立。
开发代理执行本仓库任务时，先阅读 [AGENTS.md](AGENTS.md)。
分支流程：`develop → 功能分支 → develop（集成测试）→ main`。所有新工作分支从 develop 创建，集成测试通过后才能进入 main。

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

Check each command succeeds before continuing. Open http://127.0.0.1:8232/, `/products/` and `/wp-admin/`. Local administrator: `d32editor`; its generated password is the `D32_ADMIN_PASSWORD` value in the ignored local `.env` file. Do not share or commit that file. Bootstrap refuses to rerun once `.runtime/bootstrap-complete` exists. The Products migration runs idempotently after plugin activation, preserves existing edits, and provides explicit `status`, `rollback` and `resume` actions through `scripts/products-migration.php`.

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

To recover code, use the implementation commit recorded in `docs/verification/home/artifact.json` or its ignored `.runtime/artifacts/<build_id>` copy. Restart only this project's WordPress container to clear PHP opcode cache, then rerun identity and HTTP checks. `scripts/artifact.py` packages committed theme/plugin bytes and verifies their hashes; it does not deploy anything.

The local environment binds only loopback. HOME-001, PRODUCT-000 and shared navigation/footer/menu/cookie UI are implemented. PRODUCT-000 intentionally does not create its fourteen Grade pages, two Process pages, Applications, Documents, Markets or the RFQ receiver; those routes remain genuine external 404 dependencies and Hub actions fail closed except for always-visible clean RFQ links. No analytics or optional-consent storage is active. A merge to `main` runs the complete CI suite, builds an immutable ARM64 image, deploys it to production and runs Home plus Products health checks. Production remains `noindex, nofollow` until the complete site is ready and indexing is explicitly authorized.

See `docs/handoffs/HOME-001-gate8.md` and `docs/handoffs/PRODUCT-000-gate8.md` for the historical candidate handoffs. Later independent acceptance and release records supplement those fixed historical receipts.
