# TiO₂ Malaysia — WordPress homepage

HOME-001 uses one custom PHP theme and a first-party typed-content plugin. The approved design is fixed; text, local links, hero media, grades and SEO are editable in WordPress **Site content**. The content model has no approval-state dependency.

## Local preview

Requires Docker Desktop, PowerShell, Python with BeautifulSoup, Node and Chrome for browser tests.

```powershell
./scripts/local.ps1 -Action Start
docker compose run --rm wpcli eval-file /workspace/scripts/bootstrap.php
npm ci
```

Open http://127.0.0.1:8232/ and `/wp-admin/`. Local administrator: `d32editor`; its generated password is the `D32_ADMIN_PASSWORD` value in the ignored local `.env` file. Do not share or commit that file. Bootstrap refuses to rerun once `.runtime/bootstrap-complete` exists. Docker volumes preserve content and uploaded media.

## Verification and recovery

```powershell
npx playwright test
python tests/http-contract.py
python tests/identity.py
python tests/negative-runtime.py
docker compose run --rm wpcli eval-file /workspace/scripts/snapshot.php restore /workspace/docs/verification/home/content-restored.json
```

Editor tests temporarily change content and restore it in `finally`. Negative runtime tests deliberately produce local 503 responses and restore the same snapshot; run only on this isolated preview. The snapshot restores editable content, not the entire database. It references the existing site-owned hero attachment; keep the `wp_data` and `db_data` volumes. For a fresh installation use the seed/bootstrap, which imports `content/media/hero.png`; do not copy old attachment IDs blindly.

To recover code, use the implementation commit recorded in `docs/verification/home/artifact.json` or its ignored `.runtime/artifacts/<build_id>` copy. Restart only this project's WordPress container to clear PHP opcode cache, then rerun identity and HTTP checks. `scripts/artifact.py` packages committed theme/plugin bytes and verifies their hashes; it does not deploy anything.

The local environment is noindex and binds only loopback. Only the homepage and shared navigation/footer/menu/cookie dialog are implemented. Other approved destinations remain genuine 404 dependencies. No analytics or optional-consent storage is active. Production deployment, legal pages, RFQ handling, domain changes and indexing are separate work.

See `docs/handoffs/HOME-001-gate8.md` for the candidate, verification limits and independent acceptance handoff. Keep the candidate runtime unchanged until Gate9 PASS/RETURN.
