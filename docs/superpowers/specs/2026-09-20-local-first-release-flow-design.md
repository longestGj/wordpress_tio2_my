# Local-first integration and release flow design

- Date: 2026-09-20
- Status: user-approved design; implementation pending

## 1. Purpose

The repository will stop using remote feature branches and remote pull requests as the normal integration path. Development, page acceptance and cross-page integration happen locally. The remote repository receives only the exact local `main` commit that has already passed local integration testing. A push to remote `main` then runs the complete GitHub quality suite and, only when it passes, builds and deploys the immutable production image.

This design replaces the normal path `feature branch -> remote PR to develop -> remote PR from develop to main`. It does not weaken the existing test-before-deploy gate, permit force-pushes, or treat deployment as equivalent to page acceptance.

## 2. Branch roles

- Local feature branches remain isolated work branches created from the latest local `develop`.
- Local `develop` is the authoritative integration branch. Accepted feature branches merge into it locally; it is not routinely pushed to `origin/develop`.
- Local `main` is the release branch. It must point to the exact local `develop` commit whose integrated runtime was tested.
- Remote `origin/main` is the production release input. A normal push to it starts automated quality checks and deployment.
- Remote feature branches and `origin/develop` are no longer required for the normal flow. Existing remote refs may remain as historical records but are not the current integration authority.

Only one integration/release operation may advance local `develop` or local `main` at a time. Parallel page work stays in separate worktrees and does not switch or rewrite another task's checkout.

## 3. Required sequence

1. Create a local feature branch from the latest local `develop` and develop in an isolated worktree.
2. Complete applicable self-tests, code review, evidence handoff and independent page acceptance. Record accepted tolerances and open dependencies without relabeling them as technical passes.
3. Confirm the feature worktree is clean and the accepted implementation/evidence commits are reachable from the feature branch.
4. Merge the feature branch into local `develop` with a traceable merge commit. Do not squash completed work-item commits or merge directly into `main`.
5. Build an isolated integration runtime from the resulting local `develop` commit. Run the complete repository suite plus page-to-page, shared-component, content/data-migration and failure-path checks applicable to the merged set. Bind the result to that exact commit.
6. If integration fails, keep `main` unchanged. Create a local fix branch from the failing local `develop`, merge the verified fix back into local `develop`, and repeat integration testing.
7. Fetch remote refs immediately before release. Require the current `origin/main` to be an ancestor of the tested local `develop`. If it is not, integrate the remote change into local `develop` and rerun the full affected integration suite.
8. Update local `main` by fast-forward only to the tested local `develop` commit. This prevents an untested release-only merge commit.
9. Reconfirm that local `main` equals the tested commit, the worktree is clean, the push is non-forced and the current task authorizes production release.
10. Push local `main` directly to `origin/main`.
11. The `Deploy production` workflow calls the reusable complete CI workflow. Only a successful quality job may publish the immutable ARM64 image and deploy it. Production health checks and rollback behavior remain part of the deployment workflow.
12. Record remote workflow, image, deployment and post-deployment results separately from local integration results.

## 4. GitHub controls

The repository settings must permit authorized normal pushes to `main` while continuing to prohibit force-pushes and branch deletion. The old requirement that `main` changes arrive through a `develop -> main` pull request must be removed.

The reusable CI workflow remains the single quality implementation. The production workflow already invokes it before image publication and deployment. Workflow contract tests must prove:

- a `main` push triggers the production workflow;
- the production image and deployment jobs depend on successful quality checks;
- failed quality checks prevent image publication and deployment;
- third-party actions stay commit-pinned and deployment permissions remain least-privilege;
- direct pushes do not bypass the full runtime integration suite.

PR and `develop`-push triggers may be removed or retained only as optional diagnostics; documentation must not describe them as required lifecycle steps.

## 5. Failure and recovery behavior

- Local integration failure: do not move local `main` and do not push. Fix through a new local branch and retest the resulting local `develop` commit.
- Remote quality failure after a `main` push: no image or deployment occurs. Do not rewrite or force-push history; prepare and test a follow-up fix locally, then push the next main commit.
- Image build failure: production remains on the previous image. Resolve through the same local feature/develop/main path.
- Deployment or health-check failure: use the existing server-side previous-version recovery, preserve the failed Git commit for traceability, and follow with a tested corrective commit.
- Diverged `origin/main`: do not force-push. Integrate the remote commit into local `develop`, rerun integration, then fast-forward local `main` to the newly tested result.

## 6. Documentation and implementation scope

Implementation updates the repository workflow entry points and contracts, including `AGENTS.md`, `CONTRIBUTING.md`, `README.md`, relevant production-flow design/status documents, workflow contract tests and, only where the existing files contradict this design, GitHub Actions configuration. GitHub branch protection/settings must be checked and updated to allow the new authorized direct-main path.

The implementation must not alter page content, CMS records, receiver configuration, secrets, production data, DNS or indexing policy merely to change the Git flow.

## 7. Current CONV-RFQ application

The user explicitly authorized this flow for the accepted `CONV-RFQ` candidate on 2026-09-20. The fixed implementation remains `f407f0ee526ac3d8fe1dc3efefa31364dc191030`; its Gate 9 evidence candidate remains `616662613d170420dce6bcddd5f026652bc3b0d8`. Process-document commits after that evidence head do not rewrite the closed page review.

The RFQ branch may be merged into local `develop`, tested with the already-integrated Home and Products pages, fast-forwarded into local `main`, and pushed to `origin/main` only after the exact integrated commit passes the required local suite. That push is authorized to trigger the existing automatic quality, image and production deployment flow.

This code-release authorization does not close or relabel the recorded external dependencies. Products subroutes, Privacy operations and route, Sample/Documents routes, CMP, production receiver binding and analytics/consent remain under their existing owners. Accurate unresolved links may remain fail-closed/404, the production receiver must remain fail-closed until its separate configuration is ready, and the site remains `noindex, nofollow` until explicit indexing authorization.

## 8. Success criteria

- Repository instructions consistently describe the local-first path and no longer require remote feature/develop PRs.
- Contract tests prove a direct `main` push cannot deploy before the complete remote quality suite succeeds.
- The exact locally tested integration commit becomes local and remote `main` without force-push or an intervening untested commit.
- Local integration, remote CI, image publication, deployment and post-deployment health are reported as distinct results.
- Current page acceptance records and open dependency statuses remain traceable and unchanged unless their responsible owners provide new evidence.
