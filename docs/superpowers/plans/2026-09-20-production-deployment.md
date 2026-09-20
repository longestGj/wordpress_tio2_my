# TiO₂ Products Production Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 建立从 GitHub `develop` 集成验证到 `main` 自动构建、发布和生产回滚的完整链路，并用现有首页在 `https://tio2products.com/` 验证这条链路。

**Architecture:** 将 WordPress 核心、主题、插件、WP-CLI 和发布 SHA 封装进不可变 GHCR 镜像，生产服务器只持久化 MariaDB、uploads、Caddy 证书与服务器秘密。GitHub Actions 在隔离环境先执行测试，再发布 SHA 镜像并通过受限 SSH 用户调用版本化部署脚本；脚本串行执行备份、初始化、健康检查和代码回滚。

**Tech Stack:** WordPress PHP 8.3/Apache、MariaDB 11.4、Docker Compose v2、Caddy 2.10.2、Bash、Python 3、Node.js 22、Playwright 1.63、GitHub Actions、GHCR。

**Spec:** `docs/superpowers/specs/2026-09-20-production-deployment-design.md`

## Global Constraints

- 生产域名固定为 `https://tio2products.com/`，`www.tio2products.com` 必须 301 到根域名并保留路径和查询参数。
- 生产服务器固定为 `129.146.32.192`，操作系统为 Ubuntu 24.04；公网只开放 TCP 22、80、443。
- 开发流固定为功能分支 → `develop` → 集成测试 → `main`；`main` 更新自动部署，不设置人工批准步骤。
- 新工作从最新 `develop` 创建 `codex/*` 分支；一个独立完成且验证过的工作项提交一个 commit。
- 不引入 Next.js、前后端分离或新的常驻运行服务；运行栈保持 WordPress、PHP、MariaDB、原生 CSS/JavaScript。
- 生产镜像固定为 `ghcr.io/longestgj/wordpress_tio2_my:<full-commit-sha>`；生效配置不得引用 `latest`。
- WordPress 核心、主题、插件和 WP-CLI 属于镜像；只有 MariaDB、`wp-content/uploads`、Caddy data/config、服务器 `.env`、状态和备份持久化。
- 密码、私钥、令牌、SQL dump、媒体备份和真实 `.env` 不得进入 Git、GHCR 镜像、测试证据或公开日志。
- 首阶段保持 `blog_public=0` 和 `noindex, nofollow`；全部页面和业务流程完成后以单独发布变更开放索引。
- 当前只建立服务器本地备份并保留最近 7 份成功备份；站外备份待全站完成后单独建立。
- 会修改内容或制造异常 scope 的测试只运行在专用隔离环境；生产验证只读，不覆盖历史 `docs/verification/home/` 证据。
- 代码回滚不得自动恢复数据库或媒体；数据恢复必须显式选择并验证备份。
- GitHub Actions 第三方 Action 使用完整 commit SHA；生产并发组串行运行且 `cancel-in-progress: false`。

## Review Focus

1. 非 40 位小写十六进制发布 SHA、路径分隔符或 shell 元字符必须在创建目录和执行 Compose 前被拒绝；Task 5 的 shell 合同测试覆盖这些输入。
2. 两个部署同时到达时，第二个进程必须等待同一把 `flock`，不能交叉更新 symlink、备份或容器；Task 5 的并发测试覆盖该条件。
3. 首次发布没有 `previous` 且候选健康检查失败时，必须保持 `current` 不存在、保留失败日志和持久卷，并返回非零；Task 8 的首次失败场景覆盖该条件。
4. 数据库已经安装但内容初始化标记缺失时，不得覆盖现有 `tio2_content` 或上传媒体；Task 3 的重复初始化测试覆盖该条件。
5. DNS 未生效、80/443 未开放或证书尚未签发时，外部健康检查必须失败并保持上一版本；Task 8 的失败回滚和 Task 10 的公网前置检查覆盖这些条件。

---

## File Map

- `wp-content/themes/tio2-malaysia/inc/seo.php`：从 WordPress 正式地址生成 canonical、Open Graph URL 和 JSON-LD ID。
- `wp-content/plugins/tio2-content/includes/identity.php`：规范化并输出生产发布 SHA 响应头；保留现有 scope 与本地指纹。
- `compose.yaml`：本地与 CI 环境继续使用的隔离 WordPress/MariaDB 配置，注入公开 URL 和可选发布身份。
- `tests/support/runtime.py`、`tests/support/runtime.mjs`：Python 与 Node 测试共享的 URL、输出目录、Compose 和环境文件接口。
- `tests/http-contract.py`、`tests/identity.py`、`tests/browser.spec.mjs`、`tests/editor.spec.mjs`、`tests/negative-runtime.py`、`playwright.config.js`：移除本机硬编码和历史证据写入。
- `requirements-dev.txt`：CI 使用的固定 Python 测试依赖。
- `.dockerignore`、`Dockerfile`：构建无秘密、含 WP-CLI 与代码的生产镜像。
- `scripts/bootstrap-production.php`：幂等激活主题/插件、安装首页初始内容并保护已有内容。
- `deploy/compose.production.yaml`：生产 Caddy、WordPress、MariaDB、固定卷与内部网络。
- `deploy/Caddyfile`：HTTPS、根域名、`www` 重定向、安全头和反向代理。
- `deploy/env.example`：生产环境变量名称、固定非秘密值和秘密生成命令说明。
- `deploy/scripts/common.sh`：发布 SHA、路径、Compose 命令、锁和日志的共享函数。
- `deploy/scripts/backup.sh`：数据库与 uploads 本地备份、校验及 7 份保留。
- `deploy/scripts/bootstrap.sh`：首次 core install 与后续幂等初始化的宿主入口。
- `deploy/scripts/healthcheck.sh`：内部容器和公网只读健康检查。
- `deploy/scripts/deploy.sh`：安装版本目录、备份、拉取、启动、验证和失败回滚。
- `deploy/scripts/rollback.sh`：显式切换到指定已安装版本，不恢复数据。
- `deploy/scripts/configure-env.sh`：在服务器交互生成 0600 的生产 `.env`。
- `deploy/scripts/provision-ubuntu.sh`：root 首次安装 Docker、创建 deploy 用户、目录、防火墙和备份 timer。
- `deploy/systemd/tio2products-backup.service`、`deploy/systemd/tio2products-backup.timer`：每日服务器本地备份。
- `tests/portability-test.py`、`tests/production-config-test.py`、`tests/deploy-scripts-test.sh`、`tests/production-e2e.sh`：可移植性、配置、脚本和完整发布测试。
- `.github/workflows/ci.yml`：PR、`develop` 和可复用的完整隔离测试。
- `.github/workflows/deploy-production.yml`：`main` 测试、SHA 镜像构建、GHCR 发布和自动部署。
- `.github/pull_request_template.md`：统一记录范围、验证、数据影响和外部依赖。
- `docs/operations/production-runbook.md`：首次配置、日常发布、健康检查、备份、回滚和数据恢复操作。
- `README.md`、`CONTRIBUTING.md`：更新本地、CI、分支保护和生产入口。

### Task 1: Parameterize public identity and expose the immutable release

**Files:**
- Create: `tests/php/runtime-config-test.php`
- Modify: `wp-content/themes/tio2-malaysia/inc/seo.php`
- Modify: `wp-content/plugins/tio2-content/includes/identity.php`
- Modify: `compose.yaml`

**Interfaces:**
- Consumes: WordPress `home_url('/')`, `TIO2_RELEASE`, `TIO2_SITE_SCOPE`, `WP_ENVIRONMENT_TYPE`.
- Produces: `tio2_public_base_url(): string`, `tio2_normalize_release(string): string`, response headers `X-Site-Scope` and `X-Tio2-Release`.

- [ ] **Step 1: Write the failing PHP contract**

Create a small WordPress-stub test that loads the two files and asserts URL normalization and SHA validation:

```php
<?php
define('ABSPATH', __DIR__ . '/');
define('TIO2_RELEASE', '0123456789abcdef0123456789abcdef01234567');
function add_action($hook, $callback, $priority = 10): void {}
function home_url($path = ''): string { return 'https://tio2products.com' . $path; }
function trailingslashit($value): string { return rtrim($value, '/') . '/'; }
function esc_url_raw($value): string { return $value; }
require dirname(__DIR__, 2) . '/wp-content/themes/tio2-malaysia/inc/seo.php';
require dirname(__DIR__, 2) . '/wp-content/plugins/tio2-content/includes/identity.php';
assert(tio2_public_base_url() === 'https://tio2products.com/');
assert(tio2_normalize_release(TIO2_RELEASE) === TIO2_RELEASE);
foreach (['', 'abc', strtoupper(TIO2_RELEASE), '../' . TIO2_RELEASE, TIO2_RELEASE . ';id'] as $invalid) {
    assert(tio2_normalize_release($invalid) === '');
}
echo "runtime configuration contract passed\n";
```

- [ ] **Step 2: Run the test and confirm the missing functions fail**

Run: `php -d zend.assertions=1 -d assert.exception=1 tests/php/runtime-config-test.php`

Expected: non-zero exit with `Call to undefined function tio2_public_base_url()`.

- [ ] **Step 3: Implement URL and release helpers**

Add this helper before the `wp_head` callback in `seo.php` and use `$base = tio2_public_base_url()` for canonical, `og:url`, `WebSite`, `WebPage` and every JSON-LD `@id`:

```php
function tio2_public_base_url(): string {
    return trailingslashit(esc_url_raw(home_url('/')));
}
```

Add these functions and the release header to `identity.php`:

```php
function tio2_normalize_release(string $release): string {
    return preg_match('/\A[0-9a-f]{40}\z/', $release) === 1 ? $release : '';
}
function tio2_release_sha(): string {
    return tio2_normalize_release(defined('TIO2_RELEASE') ? (string) TIO2_RELEASE : '');
}
add_action('send_headers', static function() {
    if (!tio2_site_ready()) return;
    header('X-Site-Scope: ' . (defined('TIO2_SITE_SCOPE') ? TIO2_SITE_SCOPE : 'tio2-my'));
    if ($release = tio2_release_sha()) header('X-Tio2-Release: ' . $release);
});
```

Remove the old duplicate scope callback. In `compose.yaml`, define `TIO2_PUBLIC_URL`, `TIO2_SITE_SCOPE`, and `TIO2_RELEASE` through `getenv()` in `WORDPRESS_CONFIG_EXTRA`; local defaults remain `http://127.0.0.1:8232`, `tio2-my`, and an empty release.

- [ ] **Step 4: Run PHP and live HTTP regression checks**

Run:

```powershell
php -d zend.assertions=1 -d assert.exception=1 tests/php/runtime-config-test.php
docker compose exec -T wordpress php /workspace/tests/php/content-test.php
python tests/http-contract.py
```

Expected: PHP contracts pass; homepage canonical resolves from WordPress `home`; no production release header is required in the local environment.

- [ ] **Step 5: Commit the independently verified change**

```powershell
git add tests/php/runtime-config-test.php wp-content/themes/tio2-malaysia/inc/seo.php wp-content/plugins/tio2-content/includes/identity.php compose.yaml
git diff --cached --check
git commit -m "feat: parameterize site and release identity"
```

### Task 2: Make the existing verification suite portable and evidence-safe

**Files:**
- Create: `tests/support/__init__.py`
- Create: `tests/support/runtime.py`
- Create: `tests/support/runtime.mjs`
- Create: `tests/portability-test.py`
- Create: `requirements-dev.txt`
- Modify: `tests/http-contract.py`
- Modify: `tests/identity.py`
- Modify: `tests/browser.spec.mjs`
- Modify: `tests/editor.spec.mjs`
- Modify: `tests/negative-runtime.py`
- Modify: `playwright.config.js`
- Modify: `package.json`

**Interfaces:**
- Consumes: `TEST_BASE_URL`, `EXPECTED_PUBLIC_URL`, `EXPECTED_RELEASE`, `TEST_OUTPUT_DIR`, `TEST_ENV_FILE`, `TEST_COMPOSE_FILE`.
- Produces: Python `runtime_settings() -> dict[str, str]`; Node `runtime` object; all generated evidence under `.runtime/test-results` unless explicitly overridden.

- [ ] **Step 1: Write the portability guard**

Create `tests/portability-test.py` to inspect executable test sources and reject historical evidence writes or fixed local origins:

```python
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FILES = [
    'tests/http-contract.py', 'tests/identity.py', 'tests/browser.spec.mjs',
    'tests/editor.spec.mjs', 'tests/negative-runtime.py', 'playwright.config.js',
]
for relative in FILES:
    text = (ROOT / relative).read_text(encoding='utf-8')
    assert 'docs/verification/home' not in text, relative
    assert "'http://127.0.0.1:8232'" not in text, relative
    assert '"http://127.0.0.1:8232"' not in text, relative
print('test portability guard passed')
```

- [ ] **Step 2: Run the guard and verify it reports the current hardcoded files**

Run: `python tests/portability-test.py`

Expected: FAIL naming at least `tests/http-contract.py` and `tests/browser.spec.mjs`.

- [ ] **Step 3: Add shared runtime settings**

Use these defaults in both language helpers:

```python
# tests/support/runtime.py
import os
from pathlib import Path
ROOT = Path(__file__).resolve().parents[2]
def runtime_settings():
    output = Path(os.getenv('TEST_OUTPUT_DIR', ROOT / '.runtime/test-results')).resolve()
    output.mkdir(parents=True, exist_ok=True)
    return {
        'base_url': os.getenv('TEST_BASE_URL', 'http://127.0.0.1:8232').rstrip('/'),
        'public_url': os.getenv('EXPECTED_PUBLIC_URL', 'http://127.0.0.1:8232').rstrip('/') + '/',
        'release': os.getenv('EXPECTED_RELEASE', ''),
        'output_dir': str(output),
        'env_file': os.getenv('TEST_ENV_FILE', '.env'),
        'compose_file': os.getenv('TEST_COMPOSE_FILE', 'compose.yaml'),
    }
```

```js
// tests/support/runtime.mjs
import path from 'node:path';
import fs from 'node:fs';
const outputDir=path.resolve(process.env.TEST_OUTPUT_DIR ?? '.runtime/test-results');
fs.mkdirSync(outputDir,{recursive:true});
export const runtime={
  baseURL:(process.env.TEST_BASE_URL ?? 'http://127.0.0.1:8232').replace(/\/$/,''),
  publicURL:(process.env.EXPECTED_PUBLIC_URL ?? 'http://127.0.0.1:8232').replace(/\/$/,'')+'/',
  expectedRelease:process.env.EXPECTED_RELEASE ?? '',
  outputDir,
  envFile:process.env.TEST_ENV_FILE ?? '.env',
  composeFile:process.env.TEST_COMPOSE_FILE ?? 'compose.yaml',
};
```

- [ ] **Step 4: Refactor each test to consume the shared settings**

`http-contract.py` must join requests against `base_url`, compare canonical/JSON-LD against `public_url`, validate upload URLs by parsed origin, and require `X-Tio2-Release` only when `release` is non-empty. `identity.py` must write `identity.json` to `output_dir`, compare live content hash with the database snapshot it just fetched, and stop reading or rewriting historical evidence.

The Playwright files must write screenshots and JSON to `runtime.outputDir`. `editor.spec.mjs` must read the configured env file, restore from `${runtime.outputDir}/content-before.json`, and obtain the WP-CLI path with `'/workspace/' + path.relative(process.cwd(), beforePath).split(path.sep).join('/')`. Its `finally` block remains mandatory. `browser.spec.mjs` must compare outgoing request origins with `new URL(runtime.baseURL).origin`.

Set Playwright configuration to:

```js
import {defineConfig} from '@playwright/test';
import {runtime} from './tests/support/runtime.mjs';
export default defineConfig({
  testDir:'./tests', testMatch:'*.spec.mjs', fullyParallel:false, workers:1,
  timeout:45000, expect:{timeout:5000}, outputDir:`${runtime.outputDir}/playwright`,
  use:{baseURL:runtime.baseURL, channel:process.env.CI ? undefined : 'chrome', headless:true, reducedMotion:'reduce', colorScheme:'light'},
  reporter:[['list'],['json',{outputFile:`${runtime.outputDir}/playwright-results.json`}]],
});
```

Pin Python dependencies in `requirements-dev.txt`:

```text
beautifulsoup4==4.14.2
PyYAML==6.0.3
```

Add `test:portable` and `test:php` scripts to `package.json`; keep the existing browser and HTTP commands.

- [ ] **Step 5: Run static and isolated live verification**

Run:

```powershell
python tests/portability-test.py
$env:TEST_OUTPUT_DIR='.runtime/test-results/portable'
python tests/http-contract.py
python tests/identity.py
npx playwright test tests/browser.spec.mjs tests/editor.spec.mjs
python tests/negative-runtime.py
git status --short docs/verification/home .runtime
```

Expected: all tests pass; `docs/verification/home` has no changes; generated output exists only under ignored `.runtime`.

- [ ] **Step 6: Commit the portable test harness**

```powershell
git add tests playwright.config.js package.json requirements-dev.txt
git diff --cached --check
git commit -m "test: make homepage verification portable"
```

### Task 3: Build the immutable WordPress image and idempotent production bootstrap

**Files:**
- Create: `.dockerignore`
- Create: `Dockerfile`
- Create: `scripts/bootstrap-production.php`
- Create: `tests/image-contract.sh`
- Create: `tests/bootstrap-production-test.sh`

**Interfaces:**
- Consumes: Docker build args `VCS_REF` (40 lowercase hex), `BUILD_DATE` (RFC 3339), server env `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`, `TIO2_CONTENT_INIT_VERSION`.
- Produces: image `ghcr.io/longestgj/wordpress_tio2_my:$VCS_REF`; `/usr/local/bin/wp`; `/opt/tio2/content`; `/opt/tio2/bin/bootstrap-production.php`; WordPress option `tio2_content_init_version`.

- [ ] **Step 1: Write image and bootstrap contracts before the image exists**

`tests/image-contract.sh` must reject a missing/invalid SHA, build a local tag, inspect the three OCI labels, and check that WP-CLI, the theme, plugin, seed JSON and hero image exist while `.git`, `.env`, `.runtime` and `docs` do not. `tests/bootstrap-production-test.sh` must generate its own `.runtime/bootstrap-compose.yaml` with the built image, the pinned MariaDB image and uniquely named disposable volumes, start that dedicated empty project, install WordPress, run the bootstrap twice, modify `hero.heading.1` between runs, and assert the second run preserves the edit and media count. Its `EXIT` trap removes the project and volumes, so this task does not depend on the production Compose file introduced later.

Use fixed test identities:

```bash
VALID_SHA=0123456789abcdef0123456789abcdef01234567
INVALID_SHA='../release;touch /tmp/tio2-invalid'
IMAGE="tio2-production-test:${VALID_SHA}"
```

- [ ] **Step 2: Run the contracts and confirm the missing Dockerfile/bootstrap failure**

Run from Git Bash or WSL:

```bash
bash tests/image-contract.sh
bash tests/bootstrap-production-test.sh
```

Expected: both exit non-zero because `Dockerfile` and `scripts/bootstrap-production.php` do not exist.

- [ ] **Step 3: Create the production Dockerfile and context exclusions**

Use the already pinned project images and copy WP-CLI from the CLI stage:

```dockerfile
FROM wordpress:cli-php8.3@sha256:2b5e9d4d3e51909dca1aaa4732e9f5e5bf0377c2114dbd8ff39f060bff202586 AS wpcli
FROM wordpress:php8.3-apache@sha256:65919a9ca10940feb10d9400fead0d639bf86241f47c91e2b9ea4703aa8452cf
ARG VCS_REF
ARG BUILD_DATE
RUN case "$VCS_REF" in ''|*[!0-9a-f]*) exit 64;; esac \
 && test "${#VCS_REF}" -eq 40 \
 && test -n "$BUILD_DATE"
LABEL org.opencontainers.image.source="https://github.com/longestGj/wordpress_tio2_my" \
      org.opencontainers.image.revision="$VCS_REF" \
      org.opencontainers.image.created="$BUILD_DATE"
ENV TIO2_RELEASE="$VCS_REF"
COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp
COPY wp-content/themes/tio2-malaysia /usr/src/wordpress/wp-content/themes/tio2-malaysia
COPY wp-content/plugins/tio2-content /usr/src/wordpress/wp-content/plugins/tio2-content
COPY content /opt/tio2/content
COPY scripts/bootstrap-production.php /opt/tio2/bin/bootstrap-production.php
RUN test -x /usr/local/bin/wp \
 && test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/style.css \
 && test -f /usr/src/wordpress/wp-content/plugins/tio2-content/tio2-content.php \
 && test -f /opt/tio2/content/initial-home.json \
 && chown -R www-data:www-data /usr/src/wordpress/wp-content /opt/tio2
```

`.dockerignore` must exclude `.git`, `.github`, `.env`, `.runtime`, `node_modules`, `docs`, `tests`, `deploy`, editor settings and OS metadata; explicitly retain only Dockerfile, `wp-content`, `content` and `scripts/bootstrap-production.php` inputs.

- [ ] **Step 4: Implement production content initialization with three explicit states**

`bootstrap-production.php` must require WP-CLI and production environment, activate the first-party plugin and theme, and branch as follows:

```php
$version = getenv('TIO2_CONTENT_INIT_VERSION') ?: 'home-v1';
$existing_version = get_option('tio2_content_init_version', '');
$existing_content = get_option('tio2_content', null);
if ($existing_version !== '') {
    echo wp_json_encode(['content' => 'already-initialized', 'version' => $existing_version]) . "\n";
    return;
}
if (is_array($existing_content) && !empty($existing_content['fields'])) {
    add_option('tio2_content_init_version', 'adopted-existing', '', false);
    echo wp_json_encode(['content' => 'preserved-existing', 'version' => 'adopted-existing']) . "\n";
    return;
}
```

Only the third state imports `/opt/tio2/content/media/hero.png`, validates `/opt/tio2/content/initial-home.json` through `tio2_validate_content`, stores the content, then writes `tio2_content_init_version=$version`. If validation or media import fails, it must delete the just-created attachment and leave both options absent. Every rerun must preserve existing content and uploads.

- [ ] **Step 5: Run image and clean-database bootstrap tests**

Run:

```bash
bash tests/image-contract.sh
bash tests/bootstrap-production-test.sh
```

Expected: image labels and contents match the requested SHA; the first bootstrap imports once; the second reports `already-initialized` or `preserved-existing`; the edited heading and media count do not change.

- [ ] **Step 6: Commit the immutable runtime**

```bash
git add .dockerignore Dockerfile scripts/bootstrap-production.php tests/image-contract.sh tests/bootstrap-production-test.sh
git diff --cached --check
git commit -m "feat: build immutable WordPress production image"
```

### Task 4: Define production Compose, Caddy and configuration contracts

**Files:**
- Create: `deploy/compose.production.yaml`
- Create: `deploy/Caddyfile`
- Create: `deploy/env.example`
- Create: `tests/production-config-test.py`

**Interfaces:**
- Consumes: `APP_IMAGE`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, eight WordPress key/salt values, `WP_ADMIN_*`, `PUBLIC_URL`, `SITE_SCOPE`, `RELEASE_SHA`, `ACME_EMAIL`, optional `COMPOSE_PROJECT_NAME` and `VOLUME_PREFIX`.
- Produces: Compose services `db`, `wordpress`, `caddy`; networks `edge`, `backend`; fixed named volumes `${VOLUME_PREFIX}_db`, `${VOLUME_PREFIX}_uploads`, `${VOLUME_PREFIX}_caddy_data`, `${VOLUME_PREFIX}_caddy_config`.

- [ ] **Step 1: Write the production configuration contract**

`tests/production-config-test.py` must create `.runtime/production-config.env` with generated hex test secrets, call `docker compose --env-file .runtime/production-config.env -f deploy/compose.production.yaml config --format json`, parse the result, and assert:

```python
assert set(config['services']) == {'db', 'wordpress', 'caddy'}
assert 'ports' not in config['services']['db']
assert 'ports' not in config['services']['wordpress']
assert config['services']['caddy']['ports'] == [
    {'mode':'ingress','target':80,'published':'80','protocol':'tcp'},
    {'mode':'ingress','target':443,'published':'443','protocol':'tcp'},
    {'mode':'ingress','target':443,'published':'443','protocol':'udp'},
]
assert config['services']['wordpress']['image'].endswith(':0123456789abcdef0123456789abcdef01234567')
assert all(service['restart'] == 'unless-stopped' for service in config['services'].values())
```

It must also run `caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile` using `caddy:2.10.2-alpine@sha256:4c6e91c6ed0e2fa03efd5b44747b625fec79bc9cd06ac5235a779726618e530d`, scan the rendered config for secret values, and fail if any are present.

- [ ] **Step 2: Run the contract and confirm the missing configuration failure**

Run: `python tests/production-config-test.py`

Expected: FAIL because `deploy/compose.production.yaml` is absent.

- [ ] **Step 3: Create production Compose with isolated networking and bounded logs**

Set `name: ${COMPOSE_PROJECT_NAME:-tio2products}`. Pin MariaDB to `mariadb:11.4@sha256:4f1d8d202fcf7bcb3902f63af09f9c1a050c2922a89652f22abaec0d4f015e83` and Caddy to the digest above. Use `${APP_IMAGE:?APP_IMAGE is required}` for WordPress. Mount only `${VOLUME_PREFIX:-tio2products}_uploads:/var/www/html/wp-content/uploads` into WordPress; do not mount all of `/var/www/html`.

Set production WordPress constants in `WORDPRESS_CONFIG_EXTRA`:

```php
define('WP_ENVIRONMENT_TYPE', 'production');
define('TIO2_SITE_SCOPE', getenv('SITE_SCOPE') ?: 'tio2-my');
define('TIO2_RELEASE', getenv('TIO2_RELEASE') ?: '');
define('WP_HOME', getenv('PUBLIC_URL'));
define('WP_SITEURL', getenv('PUBLIC_URL'));
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
define('WP_AUTO_UPDATE_CORE', false);
define('AUTOMATIC_UPDATER_DISABLED', true);
```

Do not set `TIO2_RELEASE` in Compose: it is baked into the image as `ENV TIO2_RELEASE=$VCS_REF`, which lets the health check detect a tag pointing at bytes built for another commit. Give MariaDB only `backend`; WordPress `backend` and `edge`; Caddy only `edge`. Configure every service with `max-size: 10m`, `max-file: 3`. MariaDB and WordPress health checks must be real readiness checks. Caddy depends on healthy WordPress.

- [ ] **Step 4: Create Caddy routing and non-secret env documentation**

Use this routing shape:

```caddyfile
{
    email {$ACME_EMAIL}
    admin off
}
www.tio2products.com {
    redir https://tio2products.com{uri} permanent
}
tio2products.com {
    encode zstd gzip
    header {
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
        X-Frame-Options SAMEORIGIN
        -Server
    }
    reverse_proxy wordpress:80
    log {
        output stdout
        format json
    }
}
```

`deploy/env.example` lists every variable, fixes `PUBLIC_URL=https://tio2products.com`, `SITE_SCOPE=tio2-my`, `TIO2_CONTENT_INIT_VERSION=home-v1`, and documents `openssl rand -hex 32` as the generator for each blank secret. It must not contain reusable example passwords.

- [ ] **Step 5: Validate configuration and secret boundaries**

Run:

```powershell
python tests/production-config-test.py
git grep -nE '(DB_PASSWORD|WP_ADMIN_PASSWORD|AUTH_KEY)=' -- ':!deploy/env.example' ':!.gitignore'
```

Expected: config and Caddy validation pass; the secret scan returns no committed assignments.

- [ ] **Step 6: Commit the production topology**

```powershell
git add deploy/compose.production.yaml deploy/Caddyfile deploy/env.example tests/production-config-test.py
git diff --cached --check
git commit -m "feat: define production container topology"
```

### Task 5: Implement safe backup, deployment and rollback scripts

**Files:**
- Create: `deploy/scripts/common.sh`
- Create: `deploy/scripts/backup.sh`
- Create: `deploy/scripts/bootstrap.sh`
- Create: `deploy/scripts/healthcheck.sh`
- Create: `deploy/scripts/deploy.sh`
- Create: `deploy/scripts/rollback.sh`
- Create: `tests/deploy-scripts-test.sh`

**Interfaces:**
- Consumes: `deploy.sh RELEASE_SHA BUNDLE_DIR`; `rollback.sh RELEASE_SHA`; `/opt/tio2products/shared/.env`.
- Produces: `releases/$RELEASE_SHA`, atomic `current` and `previous` symlinks, `shared/state/current-sha`, local backup directories, exit status 0 only after all health checks pass.

- [ ] **Step 1: Write pure shell contracts for dangerous inputs and locking**

`tests/deploy-scripts-test.sh` sources `common.sh` with `TIO2_SOURCE_ONLY=1`, uses a temporary `APP_ROOT`, and asserts:

```bash
valid=0123456789abcdef0123456789abcdef01234567
validate_release_sha "$valid"
for invalid in '' abc "${valid^^}" "../$valid" "$valid;id" "$valid/child"; do
  if validate_release_sha "$invalid"; then exit 1; fi
done
[[ "$(release_dir "$valid")" == "$APP_ROOT/releases/$valid" ]]
if release_dir "../escape" >/dev/null 2>&1; then exit 1; fi
```

Start one helper process that holds `$APP_ROOT/shared/state/deploy.lock` for two seconds, start a second helper, and assert its completion timestamp is later than the first release timestamp. Create ten valid backup directories plus a sentinel directory outside `backups`, call retention, and assert exactly seven valid backups remain while the sentinel remains.

- [ ] **Step 2: Run the shell contract and confirm missing functions fail**

Run: `bash tests/deploy-scripts-test.sh`

Expected: non-zero because `common.sh` does not exist.

- [ ] **Step 3: Implement shared invariants and Compose invocation**

`common.sh` must use `set -Eeuo pipefail`, never `set -x`, and implement these exact functions:

```bash
validate_release_sha() { [[ "${1:-}" =~ ^[0-9a-f]{40}$ ]]; }
release_dir() {
  validate_release_sha "${1:-}" || return 64
  printf '%s/releases/%s\n' "$APP_ROOT" "$1"
}
compose() {
  local dir="$1"; shift
  APP_IMAGE="$(cat "$dir/image-ref")" RELEASE_SHA="$(cat "$dir/release-sha")" \
    docker compose --project-directory "$dir" --env-file "$APP_ROOT/shared/.env" \
    -f "$dir/compose.production.yaml" "$@"
}
acquire_deploy_lock() {
  mkdir -p "$APP_ROOT/shared/state"
  exec 9>"$APP_ROOT/shared/state/deploy.lock"
  flock 9
}
```

Add `atomic_link TARGET LINK` using `ln -sfn` to a temporary sibling followed by `mv -Tf`, `log_event LEVEL MESSAGE` writing UTC JSON lines without environment values, and `prune_backups` that deletes only real paths matching `^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}$` under `$APP_ROOT/backups`.

- [ ] **Step 4: Implement verified local backup**

`backup.sh` accepts `--release RELEASE_SHA` or `--scheduled`. It creates `backups/YYYYMMDDTHHMMSSZ-${release:0:12}`, runs `mariadb-dump --single-transaction --quick --lock-tables=false` through the active Compose project, streams `uploads` from the WordPress container into `uploads.tar.gz`, and writes `metadata.json` plus `SHA256SUMS`.

Before success it must run:

```bash
test -s "$backup/database.sql"
grep -qE 'MariaDB dump|MySQL dump' "$backup/database.sql"
test -s "$backup/uploads.tar.gz"
tar -tzf "$backup/uploads.tar.gz" >/dev/null
(cd "$backup" && sha256sum -c SHA256SUMS)
```

On failure, rename the directory with `.failed`, return non-zero, and never count it toward the seven successful backups.

- [ ] **Step 5: Implement bootstrap and read-only health checks**

`bootstrap.sh RELEASE_DIR` waits for healthy `db` and `wordpress`; if `wp core is-installed` fails, it runs `wp core install` using `WP_ADMIN_*` from the server env without echoing them. It always sets `home`, `siteurl`, timezone, permalink, comments and `blog_public=0`, activates the theme/plugin, calls `/opt/tio2/bin/bootstrap-production.php`, and confirms `tio2_content` is readable.

`healthcheck.sh RELEASE_DIR` must verify Compose status, internal WordPress HTTP 200, MariaDB access and content option, then fetch `${HEALTHCHECK_BASE_URL:-$PUBLIC_URL}/` and assert status 200, the homepage H1, canonical `$PUBLIC_URL/`, `noindex, nofollow`, `X-Site-Scope: tio2-my`, and `X-Tio2-Release: $RELEASE_SHA`. In production it also verifies HTTP→HTTPS, `www` path-preserving redirect and certificate hostname/expiry. It never logs response cookies or request bodies.

- [ ] **Step 6: Implement transactional deployment and explicit code rollback**

`deploy.sh RELEASE_SHA BUNDLE_DIR` must validate arguments before filesystem writes, acquire the lock, require `.env` mode 0600, copy the four deployment files and scripts to a new release directory, write `release-sha` and `image-ref`, validate Compose, and record the old `current` target.

The mutation order is fixed:

```text
validate → lock → install release → compose config → pre-deploy backup when current exists
→ pull candidate → start db/wordpress → bootstrap → start caddy → healthcheck
→ previous=current → current=candidate → current-sha=candidate → prune old releases
```

If any step after service mutation fails, run the old release with the same volumes, call its health check, leave `current` and `current-sha` unchanged, and log `rollback_succeeded`. With no old release, stop the failed application without `--volumes`, leave `current` absent, log `first_deploy_failed`, and return non-zero.

`rollback.sh RELEASE_SHA` accepts only an installed validated release, takes the same lock, backs up current data, starts that release, verifies it, moves the former current to `previous`, atomically updates `current`, and never imports a database dump or replaces uploads.

- [ ] **Step 7: Run script contracts and shell syntax checks**

Run:

```bash
bash tests/deploy-scripts-test.sh
for file in deploy/scripts/*.sh; do bash -n "$file"; done
```

Expected: invalid inputs are rejected, lock ordering is deterministic, retention stays inside the backup root, and every script parses.

- [ ] **Step 8: Commit release mechanics**

```bash
git add deploy/scripts tests/deploy-scripts-test.sh
git diff --cached --check
git commit -m "feat: add transactional production deployment"
```

### Task 6: Add GitHub CI and automatic main deployment

**Files:**
- Create: `scripts/ci-environment.sh`
- Create: `.github/workflows/ci.yml`
- Create: `.github/workflows/deploy-production.yml`
- Create: `.github/pull_request_template.md`
- Create: `tests/workflow-contract.py`

**Interfaces:**
- Consumes: GitHub secrets `PROD_SSH_PRIVATE_KEY`, `PROD_KNOWN_HOSTS`; variables `PROD_HOST=129.146.32.192`, `PROD_PORT=22`, `PROD_USER=deploy`.
- Produces: required checks `Main source guard`, `PHP and content`, `Browser integration`, `Production deployment contract`; GHCR SHA image; one serialized production deployment.

- [ ] **Step 1: Write a workflow structure test**

`tests/workflow-contract.py` loads both YAML files with `yaml.safe_load`, normalizes YAML's `on` boolean key when required, and asserts:

```python
assert ci['on']['pull_request']['branches'] == ['develop', 'main']
assert ci['on']['push']['branches'] == ['develop']
assert deploy['on']['push']['branches'] == ['main']
assert deploy['concurrency'] == {'group':'production','cancel-in-progress':False}
assert deploy['permissions'] == {'contents':'read','packages':'write'}
assert deploy['jobs']['deploy']['needs'] == ['quality','image']
assert 'environment' not in deploy['jobs']['deploy']
```

Walk every `uses` value and require a 40-character lowercase SHA after `@`, except the local `./.github/workflows/ci.yml` reference. Assert the deploy workflow uses `github.sha` for the image tag, calls native `ssh`/`scp`, and never contains `StrictHostKeyChecking=no`, `latest`, a password variable or a database secret.

- [ ] **Step 2: Run the workflow contract and confirm files are absent**

Run: `python tests/workflow-contract.py`

Expected: FAIL because the workflow files do not exist.

- [ ] **Step 3: Add an idempotent isolated CI environment helper**

`scripts/ci-environment.sh` accepts `up`, `test`, and `down`. `up` creates `.runtime/ci.env` with random hex-only local passwords, starts `compose.yaml`, installs WordPress only when absent, runs local bootstrap once, and waits for HTTP. `test` exports the Task 2 variables, runs PHP/content, HTTP, identity, Playwright browser/editor and negative-runtime tests. `down` always executes `docker compose --env-file .runtime/ci.env down --volumes --remove-orphans`. Trap `down` in workflow jobs that mutate the database.

- [ ] **Step 4: Create the reusable CI workflow with pinned Actions**

Use these exact immutable references:

```text
actions/checkout@11d5960a326750d5838078e36cf38b85af677262
actions/setup-node@49933ea5288caeca8642d1e84afbd3f7d6820020
actions/setup-python@a26af69be951a213d495a4c3e4e4022e16d87065
actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02
```

`ci.yml` supports `pull_request`, `push` to `develop`, and `workflow_call`. `Main source guard` succeeds for non-main PRs and fails when a PR targeting main has `github.head_ref != 'develop'`. The other jobs install with `npm ci`, `pip install --requirement requirements-dev.txt`, and `npx playwright install --with-deps chromium`; they upload `.runtime/test-results` even on failure. No job writes to `docs/verification/home`.

- [ ] **Step 5: Create the main build and deployment workflow**

Use these additional pinned Actions:

```text
docker/setup-buildx-action@8d2750c68a42422c14e847fe6c8ac0403b4cbd6f
docker/login-action@c94ce9fb468520275223c153574b00df6fe4bcc9
docker/build-push-action@10e90e3645eae34f1e60eeb005ba3a3d33f178e8
```

The `quality` job calls the local reusable workflow. The `image` job builds and pushes only `ghcr.io/longestgj/wordpress_tio2_my:${{ github.sha }}` with `VCS_REF=${{ github.sha }}` and a UTC RFC 3339 `BUILD_DATE`. The `deploy` job waits for both jobs and creates the bundle with `tar -C deploy -czf deploy-bundle.tar.gz compose.production.yaml Caddyfile scripts`. It writes the SSH key to an ephemeral 0600 file, writes `PROD_KNOWN_HOSTS` verbatim, uploads the bundle to `/opt/tio2products/incoming/${{ github.sha }}.tar.gz`, and executes:

```bash
ssh -p "$PROD_PORT" "$PROD_USER@$PROD_HOST" \
  "/opt/tio2products/bin/receive-release.sh '${GITHUB_SHA}' '/opt/tio2products/incoming/${GITHUB_SHA}.tar.gz'"
```

The remote command receives no application secret. Set production concurrency to `cancel-in-progress: false`. Use `if: always()` cleanup for ephemeral SSH files.

- [ ] **Step 6: Validate workflow policy and local CI behavior**

Run:

```bash
python tests/workflow-contract.py
bash scripts/ci-environment.sh up
trap 'bash scripts/ci-environment.sh down' EXIT
bash scripts/ci-environment.sh test
```

Expected: workflow contract passes; clean isolated CI environment completes all tests and is removed with its volumes.

- [ ] **Step 7: Commit CI and deployment automation**

```bash
git add .github scripts/ci-environment.sh tests/workflow-contract.py
git diff --cached --check
git commit -m "ci: verify and deploy main automatically"
```

### Task 7: Add repeatable Ubuntu provisioning, secrets setup and daily backups

**Files:**
- Create: `deploy/scripts/receive-release.sh`
- Create: `deploy/scripts/configure-env.sh`
- Create: `deploy/scripts/provision-ubuntu.sh`
- Create: `deploy/systemd/tio2products-backup.service`
- Create: `deploy/systemd/tio2products-backup.timer`
- Create: `tests/provision-contract.py`

**Interfaces:**
- Consumes: root invocation `provision-ubuntu.sh /root/tio2-deploy.pub`; interactive `configure-env.sh`; uploaded release tarballs.
- Produces: deploy user/key, Docker Engine + Compose, `/opt/tio2products`, 0600 server env, systemd daily backup timer, UFW 22/80/443 policy.

- [ ] **Step 1: Write the provisioning policy test**

`tests/provision-contract.py` reads the scripts and unit files and asserts the root script checks Ubuntu 24.04, requires an existing public-key file, installs Docker from `download.docker.com`, enables Docker, creates a locked-password `deploy` user, grants only project ownership plus Docker group, allows SSH before enabling UFW, and never changes `PermitRootLogin`. It also asserts the env script uses `read -s`, `openssl rand -hex 32`, `umask 077`, atomic rename, and never echoes generated values.

- [ ] **Step 2: Run the policy test and confirm files are absent**

Run: `python tests/provision-contract.py`

Expected: FAIL because the provisioning scripts are absent.

- [ ] **Step 3: Implement idempotent host provisioning**

`provision-ubuntu.sh` must require root, verify `VERSION_ID=24.04`, validate the supplied line starts with `ssh-ed25519`, install Docker's official Noble repository/keyring and packages `docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin`, then:

```bash
id deploy >/dev/null 2>&1 || useradd --create-home --shell /bin/bash deploy
usermod --append --groups docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
install -m 600 -o deploy -g deploy "$PUBLIC_KEY_FILE" /home/deploy/.ssh/authorized_keys
install -d -m 750 -o deploy -g deploy /opt/tio2products/{releases,incoming,shared/state,backups,logs,bin}
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
systemctl enable --now docker
```

It copies `receive-release.sh` to `/opt/tio2products/bin/receive-release.sh`, installs the two systemd units, and starts the timer only after `current/scripts/backup.sh` exists. It does not disable root SSH.

- [ ] **Step 4: Implement secret configuration and release receiver**

`configure-env.sh` prompts without echo for WordPress admin username/password and a valid email, generates database passwords and eight WordPress keys/salts as 64 hex characters, writes fixed URL/scope/init values, validates every required name, then atomically installs `/opt/tio2products/shared/.env` as `deploy:deploy` mode 0600.

`receive-release.sh RELEASE_SHA ARCHIVE` validates both values, requires the archive real path under `/opt/tio2products/incoming`, extracts into a `mktemp` directory with `tar --no-same-owner --no-same-permissions`, rejects absolute paths and `..` members before extraction, calls the extracted `scripts/deploy.sh`, and removes the incoming archive only after success.

- [ ] **Step 5: Define daily backup units**

Use:

```ini
[Unit]
Description=TiO2 Products local production backup
Requires=docker.service
After=docker.service
[Service]
Type=oneshot
User=deploy
Group=deploy
ExecStart=/opt/tio2products/current/scripts/backup.sh --scheduled
```

```ini
[Unit]
Description=Run TiO2 Products backup daily
[Timer]
OnCalendar=*-*-* 02:20:00 UTC
Persistent=true
RandomizedDelaySec=10m
[Install]
WantedBy=timers.target
```

- [ ] **Step 6: Run provisioning policy and syntax checks**

Run:

```bash
python tests/provision-contract.py
for file in deploy/scripts/*.sh; do bash -n "$file"; done
systemd-analyze verify deploy/systemd/tio2products-backup.service deploy/systemd/tio2products-backup.timer
```

Expected: policy, syntax and systemd verification pass without changing the development machine.

- [ ] **Step 7: Commit repeatable host setup**

```bash
git add deploy/scripts/receive-release.sh deploy/scripts/configure-env.sh deploy/scripts/provision-ubuntu.sh deploy/systemd tests/provision-contract.py
git diff --cached --check
git commit -m "ops: automate production host provisioning"
```

### Task 8: Exercise first install, preservation, backup, rollback and restart locally

**Files:**
- Create: `deploy/Caddyfile.test`
- Create: `deploy/compose.test.yaml`
- Create: `tests/production-e2e.sh`
- Modify: `deploy/scripts/healthcheck.sh`

**Interfaces:**
- Consumes: production image and Task 5 scripts with test-only `COMPOSE_PROJECT_NAME`, `VOLUME_PREFIX`, `APP_ROOT`, `HEALTHCHECK_BASE_URL` overrides.
- Produces: disposable `.runtime/production-e2e` releases and evidence; zero persistent test volumes after cleanup.

- [ ] **Step 1: Write the full lifecycle test with deterministic identities**

Use three SHA values:

```bash
SHA_A=1111111111111111111111111111111111111111
SHA_B=2222222222222222222222222222222222222222
SHA_BAD=3333333333333333333333333333333333333333
```

The script must build A and B, deploy A into a dedicated project on `127.0.0.1:8244`, save a custom heading and upload marker, deploy B, and assert both persist. It must verify the pre-B backup SQL and tar checksums, stop and restart the project, and assert B plus content still respond.

For rollback, tag image B as the `SHA_BAD` tag without changing its baked `TIO2_RELEASE`; deploy `SHA_BAD`. The release must start, fail the expected release-header check, automatically restore B, and leave database/media hashes unchanged. For first-deploy failure, use a second empty `APP_ROOT` and volume prefix with the mismatched image, then assert no `current` link exists and `.failed` diagnostics remain.

- [ ] **Step 2: Run the lifecycle test before adding overrides**

Run: `bash tests/production-e2e.sh`

Expected: FAIL because local Caddy/Compose overrides are absent.

- [ ] **Step 3: Add a localhost-only Caddy and Compose override**

`Caddyfile.test` listens on `http://:80` and proxies to WordPress without ACME. `compose.test.yaml` binds Caddy only to `127.0.0.1:8244:80`, replaces the production Caddyfile mount, and keeps the same private database and WordPress topology. `healthcheck.sh` uses `HEALTHCHECK_MODE=local` to skip only DNS, redirect and certificate assertions; it still requires content, canonical, noindex, scope and release SHA.

- [ ] **Step 4: Run the complete disposable lifecycle twice**

Run:

```bash
bash tests/production-e2e.sh
bash tests/production-e2e.sh
docker volume ls --format '{{.Name}}' | grep 'tio2products-e2e' && exit 1 || true
```

Expected: both runs pass; A→B preserves data; bad release rolls back to B; first bad release leaves no current link; cleanup removes all dedicated containers, networks and volumes.

- [ ] **Step 5: Commit the end-to-end release proof**

```bash
git add deploy/Caddyfile.test deploy/compose.test.yaml deploy/scripts/healthcheck.sh tests/production-e2e.sh
git diff --cached --check
git commit -m "test: prove production deployment lifecycle"
```

### Task 9: Document operations and perform whole-branch verification

**Files:**
- Create: `docs/operations/production-runbook.md`
- Modify: `README.md`
- Modify: `CONTRIBUTING.md`
- Modify: `docs/superpowers/specs/2026-09-20-production-deployment-design.md`

**Interfaces:**
- Consumes: all commands and paths created in Tasks 1–8.
- Produces: one operator entry point for provisioning, secrets, deploy status, logs, backups, code rollback and explicit data restore.

- [ ] **Step 1: Write the runbook against executable commands**

Document exact commands for:

```bash
docker compose version
sudo -u deploy /opt/tio2products/current/scripts/healthcheck.sh /opt/tio2products/current
sudo -u deploy /opt/tio2products/current/scripts/backup.sh --scheduled
sudo -u deploy /opt/tio2products/current/scripts/rollback.sh 0123456789abcdef0123456789abcdef01234567
journalctl -u tio2products-backup.service --since today
docker compose --project-directory /opt/tio2products/current --env-file /opt/tio2products/shared/.env -f /opt/tio2products/current/compose.production.yaml ps
```

The data restore section must require maintenance mode, a fresh failure snapshot, an explicit backup path, `sha256sum -c`, SQL inspection, uploads archive listing, and separate confirmation before importing. State plainly that local backups do not survive total server loss and offsite backup remains deferred by current scope.

- [ ] **Step 2: Update repository entry points and current capability statements**

README links to the runbook, production configuration and CI. CONTRIBUTING replaces “待建立” workflow statements with actual check names and keeps the feature→develop→main rule. The spec status remains `用户已于 2026-09-20 确认`; add an implementation record section only after tests identify the exact candidate SHA.

- [ ] **Step 3: Run the whole branch verification once**

Run:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/php/runtime-config-test.php
python tests/portability-test.py
python tests/workflow-contract.py
python tests/production-config-test.py
python tests/provision-contract.py
bash tests/deploy-scripts-test.sh
bash tests/production-e2e.sh
bash scripts/ci-environment.sh up
trap 'bash scripts/ci-environment.sh down' EXIT
bash scripts/ci-environment.sh test
git diff --check
git status --short
```

Expected: every check passes; historical verification files are unchanged; only intentional documentation changes remain unstaged.

- [ ] **Step 4: Review the branch against the approved spec**

Use the repository code-review workflow. Verify every spec section has implementation and evidence, inspect secret boundaries, destructive paths, error cleanup and production-only behavior, then resolve all required findings and rerun only affected checks.

- [ ] **Step 5: Commit the runbook and verified capability record**

```bash
git add README.md CONTRIBUTING.md docs/operations/production-runbook.md docs/superpowers/specs/2026-09-20-production-deployment-design.md
git diff --cached --check
git commit -m "docs: add production operations runbook"
```

### Task 10: Integrate through develop, provision production and prove the live release

**Files:**
- Create after first release: `docs/operations/releases/2026-09-20-initial-production.md`
- Modify after first release: `docs/operations/production-runbook.md` only if actual commands differ from the tested interface.

**Interfaces:**
- Consumes: reviewed feature branch, GitHub repository settings, Alibaba Cloud DNS, Oracle Cloud ingress, root shell, production admin inputs.
- Produces: protected GitHub branches, configured deploy access, live SHA matching Git/GHCR/server/HTTP, production evidence with no secrets.

- [ ] **Step 1: Push the feature branch and open the develop PR**

```bash
git push -u origin codex/production-deployment
gh pr create --base develop --head codex/production-deployment \
  --title "Build automatic production deployment" \
  --body-file .runtime/production-pr.md
```

The PR body records final behavior, exact checks, persistent data boundaries, rollback behavior, local-backup limitation and external DNS/firewall prerequisites. Attach the created PR to the task.

- [ ] **Step 2: Wait for real GitHub checks, review, merge to develop and retest the merge commit**

```bash
gh pr checks --watch
gh pr merge --merge --delete-branch
git switch develop
git pull --ff-only origin develop
bash scripts/ci-environment.sh up
trap 'bash scripts/ci-environment.sh down' EXIT
bash scripts/ci-environment.sh test
bash tests/production-e2e.sh
```

Expected: required checks pass on the PR and the actual `develop` merge commit passes integration tests.

- [ ] **Step 3: Configure GitHub branch protection and deployment inputs**

Create `.runtime/main-protection.json` with strict required checks `Main source guard`, `PHP and content`, `Browser integration`, `Production deployment contract`, required PR reviews configured with zero approvals but PR required, conversation resolution, admin enforcement, force-push disabled and deletion disabled. Apply it with:

```bash
gh api --method PUT repos/longestGj/wordpress_tio2_my/branches/main/protection --input .runtime/main-protection.json
gh variable set PROD_HOST --body 129.146.32.192
gh variable set PROD_PORT --body 22
gh variable set PROD_USER --body deploy
gh secret set PROD_SSH_PRIVATE_KEY < .runtime/tio2-production
gh secret set PROD_KNOWN_HOSTS < .runtime/known_hosts
```

Generate `.runtime/tio2-production` with Ed25519, never print the private key, and compare the server's `/etc/ssh/ssh_host_ed25519_key.pub` fingerprint with the locally captured known-host entry before saving the secret.

- [ ] **Step 4: Provision the server from the root shell and verify deploy access**

Pass only the generated public key to the already open root shell. From the local reviewed `develop` commit, create a GitHub source archive, calculate its SHA-256 locally, then give the root shell the exact commit and archive checksum. The root shell downloads `https://github.com/longestGj/wordpress_tio2_my/archive/$DEVELOP_SHA.tar.gz`, verifies `echo "$ARCHIVE_SHA256  /root/tio2-source.tar.gz" | sha256sum -c -`, extracts it, and runs the extracted `deploy/scripts/provision-ubuntu.sh /root/tio2-deploy.pub` followed by `configure-env.sh`. Verify:

```bash
ssh -i .runtime/tio2-production deploy@129.146.32.192 'id && docker compose version && stat -c "%U:%G %a %n" /opt/tio2products/shared/.env'
```

Expected: user is `deploy`, Docker Compose works, and `.env` is `deploy:deploy 600`. Root SSH remains unchanged during initial rollout.

- [ ] **Step 5: Open the network and configure authoritative DNS before main merge**

In Oracle Cloud, allow inbound TCP 80/443 to this instance while retaining controlled 22. In Alibaba Cloud DNS, set root A to `129.146.32.192` and `www` CNAME to `tio2products.com`. Verify from an external resolver:

```bash
dig +short A tio2products.com @1.1.1.1
dig +short CNAME www.tio2products.com @1.1.1.1
nc -vz 129.146.32.192 80
nc -vz 129.146.32.192 443
```

Expected: root resolves to the production IP, `www` resolves through the root, and both ports accept connections after Caddy starts. Before Caddy starts, DNS must still be correct; an initial port refusal is recorded as expected rather than called a deployment failure.

- [ ] **Step 6: Create the public GHCR package without deploying a non-main commit**

Build the tested `develop` candidate into the package only to establish visibility; do not run it on production:

```bash
DEVELOP_SHA="$(git rev-parse develop)"
gh auth token | docker login ghcr.io -u longestGj --password-stdin
docker buildx build --push \
  --build-arg "VCS_REF=$DEVELOP_SHA" \
  --build-arg "BUILD_DATE=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --tag "ghcr.io/longestgj/wordpress_tio2_my:$DEVELOP_SHA" .
docker logout ghcr.io
```

Set the package visibility to public in GitHub package settings, then prove anonymous pull with a clean temporary Docker config. Do not store a GHCR credential on the server.

- [ ] **Step 7: Open and merge develop → main, then watch automatic production deployment**

```bash
gh pr create --base main --head develop \
  --title "Release production deployment and homepage" \
  --body-file .runtime/release-pr.md
gh pr checks --watch
gh pr merge --merge
gh run list --workflow deploy-production.yml --limit 1
gh run watch
```

Expected: main tests succeed, GHCR publishes the full main SHA tag, deployment succeeds without a manual environment gate, and the server runs that exact tag.

- [ ] **Step 8: Execute public read-only release checks**

Capture commands and sanitized results in the release record:

```bash
curl -fsSI http://tio2products.com/
curl -fsSI 'https://www.tio2products.com/test-path?source=verify'
curl -fsS -D .runtime/prod-headers.txt https://tio2products.com/ -o .runtime/prod-home.html
openssl s_client -connect tio2products.com:443 -servername tio2products.com </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName
ssh -i .runtime/tio2-production deploy@129.146.32.192 '/opt/tio2products/current/scripts/healthcheck.sh /opt/tio2products/current'
```

Assert HTTPS 200, exact path-preserving redirects, homepage content, canonical, `noindex, nofollow`, scope, main SHA header, certificate hostname/validity, admin login page reachability and no secret-bearing response headers.

- [ ] **Step 9: Verify backup, restart recovery and code-only rollback**

Run a manual verified backup, restart Docker, and rerun health checks. After a second successful main release exists, run `rollback.sh` to the first installed main SHA, verify data/media hashes remain unchanged, then redeploy the latest main SHA and verify again. Do not run SQL import or replace uploads during this exercise.

- [ ] **Step 10: Record sanitized evidence in a separate commit and pass it through develop → main**

The release record contains commit SHA, image digest, server current/previous SHA, container status, redirect/TLS/noindex checks, backup checksum verification, restart result, rollback result, remaining 24 pages and deferred offsite backup. It contains no IP-private key, passwords, cookies, SQL, media archive or `.env` values.

```bash
git switch develop
git pull --ff-only origin develop
git switch -c codex/initial-production-record
git add docs/operations/releases/2026-09-20-initial-production.md
git diff --cached --check
git commit -m "docs: record initial production release"
git push -u origin codex/initial-production-record
```

Open the feature→develop PR, wait for checks, merge, retest `develop`, then open and merge develop→main. The resulting documentation-only main release supplies a second valid production SHA; use it for the controlled code rollback exercise and restore the latest SHA afterward.

## Plan Self-Review Record

- Spec coverage: all thirteen design sections map to Tasks 1–10; GHCR visibility, DNS, Oracle ingress, branch protection, first initialization, daily backup, restart and controlled production rollback each have an explicit step.
- Placeholder scan: no deferred implementation markers or unspecified error-handling steps remain; dynamic SHA, paths and secrets are defined runtime interfaces rather than literal values.
- Interface consistency: `RELEASE_SHA`, `APP_IMAGE`, `PUBLIC_URL`, `SITE_SCOPE`, `APP_ROOT`, `HEALTHCHECK_BASE_URL`, `current`, `previous` and test environment names are consistent across image, Compose, shell, CI and runbook tasks.
- Review-focus coverage: malformed release identity and traversal are in Task 5; locking is in Task 5; first-release failure and post-start rollback are in Task 8; partial initialization is in Task 3; DNS/TLS failure and external prerequisites are in Tasks 8 and 10.
- Evidence boundary: local/CI output stays under `.runtime`; the historical homepage evidence remains immutable; the first production release gets a new sanitized record only after the live checks complete.
