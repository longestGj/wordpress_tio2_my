# Local-first Release Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace remote feature/develop PR integration with a locally integrated and tested `develop -> main` flow, then publish the accepted RFQ candidate by pushing the exact tested local `main` commit through the existing test-gated production workflow.

**Architecture:** Local feature branches merge into local `develop`; the complete Home, Products and RFQ runtime suite validates that exact integration commit. Local `main` advances by fast-forward only to the tested commit, and a normal `origin/main` push invokes the reusable CI workflow before image publication and deployment. GitHub repository rules permit ordinary main updates while blocking deletion and non-fast-forward history changes.

**Tech Stack:** Git, PowerShell, Bash, GitHub Actions, GitHub CLI/API, Python 3.12, PHP 8.3, WordPress, MariaDB, Docker Compose, Node.js 22, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-20-local-first-release-flow-design.md`

## Global Constraints

- Normal integration is local: do not push feature branches or local `develop`, and do not create feature-to-develop or develop-to-main pull requests.
- Keep the accepted RFQ implementation `f407f0ee526ac3d8fe1dc3efefa31364dc191030` and Gate 9 evidence candidate `616662613d170420dce6bcddd5f026652bc3b0d8` historically intact; later process commits do not rewrite that review.
- Never force-push or delete `main`; a remote divergence is merged into local `develop` and the result is retested.
- Local `main` must equal the exact local `develop` commit that passed the complete integration suite; no release-only merge commit may intervene.
- The `Deploy production` workflow must complete the reusable quality workflow before publishing an image or changing production.
- RFQ automated tests use only a loopback WordPress runtime and the deterministic fake receiver; do not send a real Web3Forms request or email.
- Do not alter production CMS data, external dependency status, DNS, analytics, CMP or indexing policy as part of the Git-flow change.
- Preserve `noindex, nofollow`; Products subroutes, Privacy, Sample/Documents, CMP, provider binding and analytics/consent remain open under their recorded owners.
- One completed and verified work item gets one commit; inspect staged diffs and run `git diff --cached --check` before every commit.

## Review Focus

- A dynamic `tio2-ci-*` project on an arbitrary loopback port must be accepted by RFQ data-changing tests, while non-loopback or unrelated projects must still be rejected.
- A direct `main` push must run all Home, Products and RFQ checks before image publication; removing the quality dependency must fail the workflow contract.
- Local or remote `main` divergence must stop fast-forward release and force a new integrated test run, never a force-push.
- A failed remote quality job must leave image publication and deployment unstarted; a failed deployment must preserve server rollback behavior and Git history.
- RFQ publication must not imply that its real receiver or external routes are ready, and no automated check may perform a real submission.

---

### Task 1: Establish the local integration base

**Files:**
- No file edits

**Interfaces:**
- Consumes: accepted RFQ branch `codex/conv-rfq-gate8`, local `develop`, local `main`, `origin/main`
- Produces: a local `develop` history containing the accepted RFQ branch plus every existing local/remote main commit before any integration test is run

- [ ] **Step 1: Verify the RFQ worktree and accepted history**

Run from `D:/32Wordpress_new/.worktrees/conv-rfq-gate8`:

```powershell
git status --short --branch
git merge-base --is-ancestor f407f0ee526ac3d8fe1dc3efefa31364dc191030 codex/conv-rfq-gate8
git merge-base --is-ancestor 616662613d170420dce6bcddd5f026652bc3b0d8 codex/conv-rfq-gate8
```

Expected: clean worktree and both ancestry commands exit `0`.

- [ ] **Step 2: Refresh remote facts without changing a branch**

```powershell
git fetch --prune origin
git worktree list --porcelain
git log --oneline --decorate --graph --all -20
```

Expected: the exact current `develop`, `main`, `origin/main` and parallel worktrees are visible. Stop if another worktree has `develop` or `main` checked out.

- [ ] **Step 3: Merge the accepted RFQ branch into local develop**

```powershell
git switch develop
git merge --no-ff codex/conv-rfq-gate8 -m "merge: integrate accepted RFQ page"
```

Expected: one traceable local merge commit; no remote ref changes.

- [ ] **Step 4: Make both existing main histories ancestors of develop**

```powershell
git merge-base --is-ancestor origin/main develop
git merge-base --is-ancestor main develop
```

For each nonzero result, merge that ref into `develop` with a normal merge commit:

```powershell
git merge --no-ff origin/main -m "merge: reconcile remote main before local release"
git merge --no-ff main -m "merge: reconcile local main before local release"
```

Run only the merge whose ancestry check failed. Resolve conflicts by preserving the newest working production/deployment behavior and the accepted Home, Products and RFQ implementations; do not choose an entire side wholesale.

- [ ] **Step 5: Verify the combined base before feature work**

```powershell
git status --short
git merge-base --is-ancestor origin/main develop
git merge-base --is-ancestor main develop
git merge-base --is-ancestor 616662613d170420dce6bcddd5f026652bc3b0d8 develop
```

Expected: clean status and all ancestry checks exit `0`.

### Task 2: Make reusable CI exclusive to the main release workflow

**Files:**
- Modify: `tests/workflow-contract.py`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: `.github/workflows/deploy-production.yml` job `quality` using `./.github/workflows/ci.yml`
- Produces: a reusable-only CI workflow and a contract proving `image` and `deploy` cannot run before `quality`

- [ ] **Step 1: Create the local process branch**

```powershell
git switch -c codex/local-first-release-flow
```

Expected: the new branch starts at the combined local `develop` commit.

- [ ] **Step 2: Write the failing workflow contract**

Replace the old PR/develop trigger and source-guard assertions in `tests/workflow-contract.py` with:

```python
assert set(ci['on']) == {'workflow_call'}
assert deploy['on']['push']['branches'] == ['main']
assert deploy['jobs']['quality']['uses'] == './.github/workflows/ci.yml'
assert deploy['jobs']['image']['needs'] == 'quality'
assert deploy['jobs']['deploy']['needs'] == ['quality', 'image']

expected_names = {
    'PHP and content', 'Browser integration', 'Production deployment contract',
}
assert {job['name'] for job in ci['jobs'].values()} == expected_names
```

- [ ] **Step 3: Run the contract and verify RED**

```powershell
python tests/workflow-contract.py
```

Expected: FAIL because `ci.yml` still has PR/develop triggers and `Main source guard`.

- [ ] **Step 4: Implement the minimal workflow change**

Change the top of `.github/workflows/ci.yml` to:

```yaml
name: CI

on:
  workflow_call:
```

Remove the complete `source-guard` job. Leave the PHP/content, browser integration and production-contract jobs unchanged.

- [ ] **Step 5: Verify GREEN and workflow syntax**

```powershell
python tests/workflow-contract.py
python tests/production-config-test.py
```

Expected: both commands pass.

- [ ] **Step 6: Commit the workflow gate**

```powershell
git add .github/workflows/ci.yml tests/workflow-contract.py
git diff --cached --check
git commit -m "ci: make main push the remote release gate"
```

### Task 3: Allow RFQ tests in dynamic isolated CI runtimes

**Files:**
- Modify: `tests/runtime-settings-test.py`
- Modify: `tests/support/runtime.py`
- Modify: `tests/rfq-http.py`
- Modify: `tests/rfq-endpoint.py`
- Modify: `tests/rfq-isolation.py`
- Modify: `tests/rfq-migration.py`

**Interfaces:**
- Produces: `is_isolated_runtime(base_url: str, project: str, dedicated_project: str) -> bool`
- Consumes: `TEST_BASE_URL`, `COMPOSE_PROJECT_NAME`; dedicated project `d32-conv-rfq-gate8`; CI prefix `tio2-ci-`

- [ ] **Step 1: Write failing runtime-policy tests**

Extend `tests/runtime-settings-test.py` with literal expectations:

```python
from support.runtime import is_isolated_runtime, runtime_settings, workspace_container_path

assert is_isolated_runtime('http://127.0.0.1:8242', 'd32-conv-rfq-gate8', 'd32-conv-rfq-gate8')
assert is_isolated_runtime('http://localhost:49152', 'tio2-ci-12345', 'd32-conv-rfq-gate8')
assert is_isolated_runtime('http://[::1]:49152', 'tio2-ci-run', 'd32-conv-rfq-gate8')
assert not is_isolated_runtime('https://tio2products.com', 'tio2-ci-run', 'd32-conv-rfq-gate8')
assert not is_isolated_runtime('http://127.0.0.1:49152', 'shared-preview', 'd32-conv-rfq-gate8')
```

- [ ] **Step 2: Run the test and verify RED**

```powershell
python tests/runtime-settings-test.py
```

Expected: FAIL with an import error for `is_isolated_runtime`.

- [ ] **Step 3: Implement the runtime policy once**

Add to `tests/support/runtime.py`:

```python
from urllib.parse import urlsplit


def is_isolated_runtime(base_url: str, project: str, dedicated_project: str) -> bool:
    parsed = urlsplit(base_url)
    return (
        parsed.scheme == 'http'
        and parsed.hostname in {'127.0.0.1', 'localhost', '::1'}
        and (project == dedicated_project or project.startswith('tio2-ci-'))
    )
```

- [ ] **Step 4: Verify the policy test turns GREEN**

```powershell
python tests/runtime-settings-test.py
```

Expected: PASS.

- [ ] **Step 5: Replace duplicated RFQ guards**

In each of the four RFQ Python tests, import `is_isolated_runtime` from `support.runtime` and reject the runtime unless:

```python
is_isolated_runtime(BASE_URL, PROJECT, 'd32-conv-rfq-gate8')
```

Retain the existing error messages and all data restoration logic. Do not broaden the receiver mode or permit HTTPS/remote hosts.

- [ ] **Step 6: Run static and policy checks**

```powershell
python -m py_compile tests/support/runtime.py tests/rfq-http.py tests/rfq-endpoint.py tests/rfq-isolation.py tests/rfq-migration.py
python tests/runtime-settings-test.py
python tests/portability-test.py
```

Expected: all commands pass.

- [ ] **Step 7: Commit the dynamic isolation policy**

```powershell
git add tests/support/runtime.py tests/runtime-settings-test.py tests/rfq-http.py tests/rfq-endpoint.py tests/rfq-isolation.py tests/rfq-migration.py
git diff --cached --check
git commit -m "test: allow RFQ checks in isolated CI runtimes"
```

### Task 4: Add RFQ to the complete remote quality suite

**Files:**
- Modify: `tests/workflow-contract.py`
- Modify: `scripts/ci-environment.sh`

**Interfaces:**
- Consumes: dynamic Compose environment from `.runtime/ci.env`, deterministic local fake receiver, Home/Products/RFQ test entry points
- Produces: one fresh CI runtime that executes all three page suites and restores data before final HTTP assertions

- [ ] **Step 1: Write the failing CI inventory contract**

Add to `tests/workflow-contract.py`:

```python
ci_script = (ROOT / 'scripts/ci-environment.sh').read_text(encoding='utf-8')
for required in [
    'TIO2_RFQ_RECEIVER_MODE=fake',
    'tests/php/rfq-model-test.php',
    'tests/php/rfq-submission-test.php',
    'tests/php/rfq-route-test.php',
    'python tests/rfq-http.py',
    'python tests/rfq-endpoint.py',
    'python tests/rfq-isolation.py',
    'python tests/rfq-migration.py',
    'tests/rfq-browser.spec.mjs',
    'tests/rfq-editor.spec.mjs',
]:
    assert required in ci_script, required
```

- [ ] **Step 2: Run the contract and verify RED**

```powershell
python tests/workflow-contract.py
```

Expected: FAIL on the first missing RFQ CI entry.

- [ ] **Step 3: Configure only the isolated CI receiver**

Add this line to the generated `.runtime/ci.env` block in `scripts/ci-environment.sh`:

```bash
TIO2_RFQ_RECEIVER_MODE=fake
```

In `run_tests`, set:

```bash
export RFQ_EVIDENCE_DIR="$TEST_OUTPUT_DIR/rfq"
```

This value reaches only the dynamic local container. Do not set `TIO2_RFQ_RECEIVER_ENABLED` or a Web3Forms key.

- [ ] **Step 4: Add RFQ server and browser coverage**

Add these commands to `run_tests` alongside the existing Home and Products checks:

```bash
compose exec -T wordpress php /workspace/tests/php/rfq-model-test.php
compose run --rm --no-deps --entrypoint php wpcli /workspace/tests/php/rfq-submission-test.php
compose run --rm wpcli eval-file /workspace/tests/php/rfq-route-test.php
python tests/rfq-http.py
python tests/rfq-endpoint.py
npx playwright test tests/browser.spec.mjs tests/editor.spec.mjs tests/products-browser.spec.mjs tests/products-editor.spec.mjs tests/rfq-browser.spec.mjs tests/rfq-editor.spec.mjs
python tests/rfq-migration.py
python tests/rfq-isolation.py
```

Keep only one combined Playwright invocation. After all data-changing checks, rerun:

```bash
python tests/http-contract.py
python tests/products-http.py
python tests/rfq-http.py
python tests/identity.py
```

- [ ] **Step 5: Verify the static contract turns GREEN**

```powershell
python tests/workflow-contract.py
bash -n scripts/ci-environment.sh
```

Expected: both pass.

- [ ] **Step 6: Commit the complete suite definition**

```powershell
git add scripts/ci-environment.sh tests/workflow-contract.py
git diff --cached --check
git commit -m "test: include RFQ in release quality suite"
```

### Task 5: Update repository process documentation

**Files:**
- Modify: `AGENTS.md`
- Modify: `CONTRIBUTING.md`
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-09-20-production-deployment-design.md`

**Interfaces:**
- Consumes: approved local-first design and the actual GitHub workflows
- Produces: one unambiguous process entry point for future development agents and maintainers

- [ ] **Step 1: Update the authoritative Git rules**

In `AGENTS.md`, replace remote PR requirements with these requirements:

```text
All feature/fix/docs branches start from the latest local develop and merge back to local develop.
Do not routinely push feature branches or develop and do not require PRs for local integration.
After the exact local develop commit passes integration, fast-forward local main to it.
A normal origin/main push is the release action; never force-push or bypass its automatic quality workflow.
```

Keep the existing clean-worktree, one-work-item-per-commit, evidence and authorization rules.

- [ ] **Step 2: Rewrite the lifecycle tables and branch roles**

Update `CONTRIBUTING.md` so the authoritative sequence is:

```text
需求明确 → 本地功能分支 → 本地验证与独立验收 → 合入本地 develop → 本地集成测试 → 本地 main 快进 → 推送 origin/main → 自动测试与发布 → 归档
```

Replace PR-specific completion requirements with local review/evidence requirements, document divergence handling, and state that `origin/develop` and remote feature branches are not normal workflow inputs. Add RFQ to the current baseline while retaining all open dependencies and user tolerances exactly.

- [ ] **Step 3: Update the concise operator entry point**

Update `README.md` to name Home, Products and RFQ as implemented pages and describe:

```text
local develop integration -> exact tested local main -> origin/main push -> reusable CI -> immutable image -> production deploy
```

Do not state that Privacy, Sample/Documents, child Product routes, the real RFQ receiver, CMP, analytics or indexing are ready.

- [ ] **Step 4: Preserve the old deployment spec as history**

Add a notice at the beginning of `docs/superpowers/specs/2026-09-20-production-deployment-design.md` stating that its remote-PR branch-flow sections are superseded by `2026-09-20-local-first-release-flow-design.md`, while its image, server, health-check and rollback architecture remains active.

- [ ] **Step 5: Review documentation consistency**

```powershell
rg -n "through PR|通过 PR|PR 合回|develop → main.*PR|feature.*PR|功能分支.*PR" AGENTS.md CONTRIBUTING.md README.md docs/superpowers/specs/2026-09-20-production-deployment-design.md
git diff --check
```

Expected: no active instruction still requires remote integration PRs; historical text is explicitly marked superseded; no whitespace errors.

- [ ] **Step 6: Commit the repository rules**

```powershell
git add AGENTS.md CONTRIBUTING.md README.md docs/superpowers/specs/2026-09-20-production-deployment-design.md
git diff --cached --check
git commit -m "docs: adopt local-first integration and release"
```

### Task 6: Merge the process work and run the full local integration gate

**Files:**
- Runtime output only: `.runtime/test-results/ci/`
- No committed evidence rewrites

**Interfaces:**
- Consumes: `codex/local-first-release-flow`
- Produces: one clean local `develop` commit with passing static, production-contract and full fresh runtime integration evidence

- [ ] **Step 1: Review and merge the process branch locally**

```powershell
git status --short
git diff develop...codex/local-first-release-flow --check
git switch develop
git merge --no-ff codex/local-first-release-flow -m "merge: adopt local-first release flow"
```

Expected: clean merge and no remote update.

- [ ] **Step 2: Run static and deployment-contract checks**

```powershell
python tests/workflow-contract.py
python tests/production-config-test.py
python tests/provision-contract.py
python tests/portability-test.py
python tests/runtime-settings-test.py
node tests/runtime-settings-test.mjs
bash tests/deploy-scripts-test.sh
bash tests/image-contract.sh
bash tests/bootstrap-production-test.sh
```

Expected: all commands exit `0`.

- [ ] **Step 3: Start a fresh combined integration environment**

First verify any existing `.runtime/ci.env` belongs to a `tio2-ci-*` project before removing it with the script. Then run:

```powershell
$env:GITHUB_RUN_ID="local-rfq-release-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
bash scripts/ci-environment.sh up
```

Expected: fresh loopback WordPress and MariaDB containers become healthy, Home/Products/RFQ content is bootstrapped, and no production system is contacted.

- [ ] **Step 4: Run the complete integration suite**

```powershell
bash scripts/ci-environment.sh test
```

Expected: every Home, Products, RFQ, browser/editor, migration, isolation, receiver-fake, identity and final-restoration check passes. No real submission occurs.

- [ ] **Step 5: Always stop and remove only the dynamic test environment**

```powershell
bash scripts/ci-environment.sh down
```

Expected: only the generated `tio2-ci-*` containers and volumes are removed; historical Gate 8 evidence and other worktrees remain untouched.

- [ ] **Step 6: Bind the result to the exact commit**

```powershell
git status --short
git rev-parse develop
git rev-parse HEAD
git diff --check
```

Expected: clean status and identical `develop`/`HEAD` SHAs. Record that SHA with the command results before moving `main`.

### Task 7: Protect main history and fast-forward the local release branch

**Files:**
- GitHub repository ruleset only; no repository file edit

**Interfaces:**
- Consumes: GitHub repository `longestGj/wordpress_tio2_my`, tested local `develop`
- Produces: active `main` history protection plus local `main == develop`

- [ ] **Step 1: Recheck remote and local ancestry after testing**

```powershell
git fetch --prune origin
git merge-base --is-ancestor origin/main develop
git merge-base --is-ancestor main develop
```

Expected: both exit `0`. If either fails, return to Task 1 reconciliation and rerun Task 6; do not continue.

- [ ] **Step 2: Confirm current GitHub rule state**

```powershell
gh api repos/longestGj/wordpress_tio2_my/rulesets
gh api repos/longestGj/wordpress_tio2_my/branches/main
```

Expected before migration: no conflicting active ruleset; direct normal main updates are allowed.

- [ ] **Step 3: Create main history protection**

Use the GitHub Rulesets API to create one active branch ruleset with:

```json
{
  "name": "Protect main history",
  "target": "branch",
  "enforcement": "active",
  "conditions": {"ref_name": {"include": ["refs/heads/main"], "exclude": []}},
  "rules": [{"type": "deletion"}, {"type": "non_fast_forward"}]
}
```

Do not add a pull-request rule or a required pre-push status check, because the approved architecture runs the quality gate after the main push and before deployment.

- [ ] **Step 4: Read back and verify the ruleset**

```powershell
gh api repos/longestGj/wordpress_tio2_my/rulesets
```

Expected: `Protect main history` is active for `refs/heads/main` with deletion and non-fast-forward rules only.

- [ ] **Step 5: Fast-forward local main to the tested commit**

```powershell
$tested = git rev-parse develop
git switch main
git merge --ff-only develop
if ((git rev-parse main) -ne $tested) { throw 'main differs from tested develop' }
git status --short
```

Expected: local `main`, `develop` and `$tested` are identical; status is clean.

### Task 8: Push main, observe the gated deployment and verify production

**Files:**
- Runtime/remote evidence only; no source edit

**Interfaces:**
- Consumes: clean local `main`, active main-history ruleset, `Deploy production` workflow
- Produces: `origin/main` at the tested commit, successful remote quality/image/deploy jobs, and read-only production verification

- [ ] **Step 1: Perform the final pre-push release check**

```powershell
$release = git rev-parse main
if ((git rev-parse develop) -ne $release) { throw 'develop/main mismatch' }
if ((git status --porcelain).Length -ne 0) { throw 'dirty release worktree' }
git merge-base --is-ancestor origin/main main
```

Expected: clean exact tested commit and ancestry exit `0`.

- [ ] **Step 2: Push only main without force**

```powershell
git push origin main
```

Expected: `origin/main` advances to `$release`; no feature or develop ref is pushed.

- [ ] **Step 3: Locate and follow the exact production workflow**

```powershell
gh run list --workflow deploy-production.yml --commit $release --limit 5
$runId = gh run list --workflow deploy-production.yml --commit $release --limit 1 --json databaseId --jq '.[0].databaseId'
if (-not $runId) { throw 'production workflow run was not created' }
gh run watch $runId --exit-status
```

Expected: the exact SHA run succeeds in this order: reusable quality, immutable image, deployment. If it fails, inspect with `gh run view $runId --log-failed`, report the failed job, and stop without rewriting Git history.

- [ ] **Step 4: Verify remote identity**

```powershell
git fetch origin main
if ((git rev-parse origin/main) -ne $release) { throw 'origin/main differs from release' }
```

Expected: local `main`, local `develop` and `origin/main` equal the same tested SHA.

- [ ] **Step 5: Perform read-only production checks**

Check `https://tio2products.com/`, `/products/` and `/request-a-quote/` without submitting a form. Verify HTTP 200, expected page identity, release/build marker, HTTPS, `tio2-my`, shared Chrome and `noindex, nofollow`. Confirm Privacy, Sample/Documents, child Product targets and production receiver readiness remain reported according to their actual state.

- [ ] **Step 6: Report the four distinct outcomes**

Report separately:

```text
Local integration: exact commit and suite result
Remote quality/image: GitHub run URL and status
Production deployment: exact commit/image and health result
Open dependencies: unchanged owners/status; no real RFQ submission performed
```

Do not call an open dependency complete merely because the RFQ page code is deployed.
