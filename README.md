# TiO₂ Malaysia — WordPress site

HOME-001 and CONV-RFQ use one custom PHP theme and a first-party typed-content plugin. The approved designs are fixed; page copy, local links, form options, hero media, grades and SEO are editable in WordPress **Site content**. Home and RFQ use independent content records and have no approval-state runtime dependency.

开发、分支、提交、验证与验收约定见 [开发流程](CONTRIBUTING.md)。该文档同时记录首页最新验收状态和待建立的 GitHub 仓库能力。
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

Check each command succeeds before continuing. Open http://127.0.0.1:8232/ and `/wp-admin/`. Local administrator: `d32editor`; its generated password is the `D32_ADMIN_PASSWORD` value in the ignored local `.env` file. Do not share or commit that file. Bootstrap refuses to rerun once `.runtime/bootstrap-complete` exists. Docker volumes preserve content and uploaded media. A fresh-volume reinstall was not part of final candidate testing; this sequence documents the original installation prerequisites.

## Verification and recovery

```powershell
npx playwright test
python tests/http-contract.py
python tests/identity.py
python tests/negative-runtime.py
docker compose run --rm wpcli eval-file /workspace/scripts/snapshot.php restore /workspace/docs/verification/home/content-restored.json
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

The local environment is noindex and binds only loopback. The homepage, RFQ page and shared navigation/footer/menu/cookie dialog are implemented. Products is not present on the CONV-RFQ branch and remains an integration dependency; the other approved destinations are genuine 404 dependencies. No analytics or optional-consent storage is active. The production RFQ adapter stays disabled and fail-closed unless production-only configuration is supplied. Production provider binding, legal pages, domain changes, indexing and deployment are separate work.

See `docs/handoffs/HOME-001-gate8.md` and `docs/handoffs/CONV-RFQ-gate8.md` for their candidates, verification limits and independent acceptance handoffs. Keep a handed-off candidate runtime unchanged until Gate9 PASS/RETURN.
