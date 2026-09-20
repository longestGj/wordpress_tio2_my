# 开发流程

适用项目：`longestGj/wordpress_tio2_my`。本文件是本仓库后续开发的执行约定。GitHub 自动检查、`develop → main` 来源检查和 main 自动生产部署已经配置；每次变更仍以对应工作流的实际结果为准。

## 1. 一项需求怎样完成

需求明确 → 接收设计与内容 → 拆分任务 → 从 develop 创建功能分支 → 本地开发与验证 → 分项提交 → PR 与代码审查 → 合入 develop → 集成测试 → develop 合入 main → 归档。

页面任务同时完成适用的独立页面验收；页面验收与 develop 集成测试都满足后，才进入 main。

部署、线上验证与回滚由 main 发布流程执行。页面开发完成、页面验收通过、全站集成完成和发布成功分别记录。

| 阶段 | 要做的事 | 完成标准与记录 |
|---|---|---|
| 需求接收 | 确认页面或功能、目标、范围、输入版本、验收条件与外部依赖 | 写清做什么、做到什么程度；已有批准结论直接沿用，不重复索取 |
| 技术设计 | 确定模板、内容字段、共享组件、路由与数据变化；小改动用简短说明 | 新架构或接口变化有书面设计；普通文字调整不展开架构设计 |
| 任务拆分 | 分成能够独立实现、检查、说明的工作项 | 每项列出改动范围、验证方式、预期结果 |
| 开发 | 从最新 develop 创建功能分支，按任务实现 | 代码与内容分离，遵守本站数据边界；不夹带其他需求 |
| 本地验证 | 运行与变更相关的检查，检查实际页面或后台行为 | 有真实结果；临时编辑已恢复；失败原因与未测项如实记录 |
| 分项提交 | 检查差异，提交完成且已验证的工作项 | 一个独立完成项一个 commit，提交信息说明目的 |
| PR 与审查 | 推送功能分支，创建目标为 develop 的 PR，执行已配置检查并审查代码 | 必修问题关闭；记录检查结果、影响、数据变化和剩余依赖 |
| 独立验收 | 对照批准输入检查固定候选的页面、内容与交互 | 结论绑定实现版本、内容与环境；自检不能替代独立验收 |
| 集成测试 | 功能分支合入 develop 后，对合并结果测试 | 页面间、共享组件、内容与数据兼容性检查通过，结果绑定 develop 的具体提交 |
| 合入 main 与归档 | 集成测试及适用验收通过后，创建 develop → main 的 PR 并合并 | main 接收已验证版本，保存验收结论和待办归属；部署单独记录 |

没有新增风险、改动或失败，不反复重跑已经通过的相同检查。验收有明确 Finding 时，只针对该问题修复和回归。

## 2. 分支和提交

- `develop`：开发与集成分支，是所有新工作分支的起点，也是其 PR 的合并目标。
- `main`：接收 develop 集成测试通过的版本；不直接接收功能分支，不直接在其上开发。
- `codex/<任务>-<简述>`：从 develop 创建的功能分支，例如 `codex/home-001-wordpress`。
- `codex/fix-<简述>`、`codex/docs-<简述>`：修复与文档分支，同样从 develop 创建并合回 develop。
- 远端 `develop` 与 `main` 已建立。GitHub CI 检查目标为 develop/main 的 PR，并拒绝非 develop 来源的 main PR；main push 自动执行生产发布工作流。

新任务开始前获取远端最新状态，更新本地 develop，再从该提交创建工作分支。不要直接从 main 或其他未合并的功能分支开始新任务。

集成失败时，从当前 develop 创建修复分支，经验证与审查合回 develop，再检查修复后的集成结果。失败未解决前不合入 main。develop 在测试通过后若又有新提交，原结果不自动覆盖新候选；应对新版本执行受影响的集成检查。

从 develop 合入 main 的版本须与测试通过的候选一致；如合并产生冲突解决或新的代码组合，必须验证实际合并结果，不能直接套用旧结论。

开始工作先检查 `git status`、当前分支和最近提交。已有未提交改动要辨明归属；不覆盖或顺手提交其他任务的文件。多个实现任务并行时使用独立 worktree，并使用独立运行环境和数据卷。

提交前使用 `git diff`、`git diff --cached` 和 `git diff --check` 检查内容，明确指定本任务文件进行暂存。

提交信息示例：

```text
feat: add editable application page content
fix: restore menu focus after closing
test: verify document request validation
docs: define repository development workflow
```

commit 是本地记录；push 是同步到 GitHub；merge 是合入目标分支；deploy 是更新运行站点。四个动作分别报告，不把“已提交”写成“已发布”。不随意 squash 已按完成项保存的提交；确需整理历史时在 PR 中说明。

## 3. WordPress 开发边界

| 内容 | 管理位置 | 约定 |
|---|---|---|
| 页面结构、样式、交互 | `wp-content/themes/tio2-malaysia/` | 固定模板、共享组件统一维护 |
| 内容模型、校验、后台编辑 | `wp-content/plugins/tio2-content/` | 权限、nonce、字段校验、站点归属检查齐全 |
| 日常文案、链接、图片引用、SEO | WordPress 数据库及媒体库 | 从后台编辑，不为改字修改模板 |
| 初次导入内容 | `content/` | 仅用于初始化；正常更新代码不覆盖已编辑的数据库内容 |
| 验证脚本 | `tests/`、`scripts/` | 明确运行环境、是否修改数据、恢复方式 |
| 交付和验收证据 | `docs/handoffs/`、`docs/verification/` | 历史记录保留，新结论单独补充 |

新增页面先复用 Header、Footer、Menu、Cookie Settings 等共享实现。更改共享组件时检查首页及其他受影响页面。

需要更改内容字段或数据库结构时，写明现有数据兼容方式、迁移步骤和恢复条件。不能靠重新执行首页初始化来完成升级。

缺少其他页面或接口时保留真实依赖，记录目标、负责人和影响，不用假成功响应掩盖。审批记录、设计编号和交付材料不能成为访客页面的运行依赖。

## 4. 按改动选择验证

| 改动 | 至少验证 |
|---|---|
| 文档 | 路径、命令与当前代码一致；无矛盾或错误状态；差异无格式错误 |
| PHP、字段校验或权限 | PHP 语法、相关内容断言、有效和无效输入、权限边界 |
| 模板、内容输出或 SEO | 实际 HTML、转义、标题/链接、元数据和结构化数据 |
| 样式、共享布局或交互 | 受影响宽度与页面、键盘/焦点、实际截图；共享布局覆盖五种既定宽度 |
| 后台编辑或媒体 | 保存后前台生效、拒绝非法更新、恢复原内容；不只检查后台提示 |
| 数据迁移或环境配置 | 独立环境演练、原数据保留、失败处置和恢复结果 |

当前已有检查入口：

```powershell
docker compose exec -T wordpress php /workspace/tests/php/content-test.php
python tests/http-contract.py
npx playwright test
python tests/identity.py
python tests/negative-runtime.py
```

PRODUCT-000 另有 `tests/products-http.py`、`tests/products-browser.spec.mjs`、`tests/products-editor.spec.mjs`、`tests/products-readiness.py` 与 `tests/products-migration.py`。会修改数据的 Products 测试只接受专用本地 `d32-product-000` 或 `scripts/ci-environment.sh` 创建的动态 `tio2-ci-*` 环境，并要求 loopback HTTP 地址；脚本会拒绝共享站和远程站。首页回归证据通过独立测试输出目录重定向，不能覆盖 `docs/verification/home/`。

这些检查覆盖当前首页、Products Hub 和共用运行层；新增页面仍需增加对应契约和真实行为检查：

- GitHub Browser integration 使用动态项目名、端口、数据卷和证据目录，在干净环境启动 WordPress 后运行首页与 Products 套件。
- 编辑测试和隔离异常测试会修改数据，只能在专用验证环境运行，不在共享验收站或线上直接执行。
- 部分测试会重写 `docs/verification/home/`。已验收证据保留原记录，新任务使用自己的证据目录；不能直接覆盖旧证据后继续声称是原候选。
- 新页面上线后，旧的“其他目标必须返回404”检查必须按新范围更新，不能为通过旧测试保留错误行为。
- PRODUCT-000 的真实路由状态测试只创建带 `_tio2_test_fixture=products-readiness` 的临时页面，清理时不得操作其他页面。Products migration 的 rollback/resume 只管理 `_tio2_managed_page=1` 且 Page ID/scope 匹配的 Hub 页面。
- 结果分别标记通过、失败、未测试和用户范围例外；豁免不等于实测通过，也不自动扩大到其他任务。

## 5. PR、代码审查和页面验收

每个 PR 至少说明：

1. 解决的问题、最终行为和改动范围。
2. 已执行的检查及结果，必要的页面截图。
3. 是否涉及内容字段、数据库或媒体，以及兼容/恢复说明。
4. 未测项、外部依赖及其负责人。
5. 对应需求、页面验收记录或待验收候选。

代码审查检查实现质量；页面验收检查是否符合批准内容、设计和业务行为。两者不互相替代。已有首页采用 D23 Gate8 开发交付、Gate9 独立验收接口，后续适用页面继续沿用其既有格式，不在本仓库另设重复审批编号。

验收候选至少记录实现 commit、证据 commit、实际制品身份、内容快照身份和运行地址。纯文档后续提交不会自动产生新的页面候选，也不会把旧页面的验收结果转移给新代码。

旧清单若要求 HEAD 等于当时证据提交，应在该历史版本的隔离 checkout 中复核。不能为了匹配当前 HEAD 改写已验收清单、重新包装历史证据或重置正在使用的工作区。

## 6. 完成标准

一项开发任务可标记完成，需要：范围内功能完成、相关检查通过、必修问题关闭、临时数据恢复、代码提交、说明和剩余依赖清楚。

页面需取得适用的独立验收结论，才标记“页面验收通过”。如果用户批准范围例外，记录适用候选与未测事实。外部目标未完成时，可以页面验收通过而集成尚未完成。

发布由 `.github/workflows/deploy-production.yml` 执行：完整 CI 通过后构建不可变 ARM64 镜像，经固定主机密钥发布到生产服务器，运行数据库、容器、首页、Products、HTTPS 与版本标记健康检查；失败时由服务器脚本恢复前一版本。数据库/媒体与代码制品分开处理。当前按用户决定暂不开启站外备份，站点完整上线后再建立固定备份；当前也保持 `noindex, nofollow`，直到全部页面完成并明确授权索引。

## 7. 当前基线

首页实现候选 `a75572a36cc50e820b640fca663a3a60594029cb`、证据提交 `9de5ef0e3409daf9c9cb675efb26f54d7c7ba6c6` 已完成独立页面验收，结论为 `READ_ONLY_QA_APPROVED / CLOSED`（用户范围例外）。物理触控、读屏和原生200%缩放保留 `NOT_TESTED`，本轮不再索取补证。

正式决定来源：`D:/23MySec/pages/home/07_qa/HOME-001_D32_GATE9_USER_EXCEPTION_CLOSEOUT_V1.0.md`，决定ID `HOME-D32-G9-UD01`。该路径是本机追溯来源，不作为 GitHub CI 或站点运行依赖。旧 Gate8 回执是历史交付状态，不能据其旧文字重新打开已关闭问题。

PRODUCT-000 实现候选 `95ed4c4`、证据提交 `8a4e3f5` 已完成独立页面验收，结论为 `PASS / CLOSED`。正式决定来源：`D:/23MySec/pages/products/05_review/PRODUCT-000_D32_GATE9_TARGETED_RECHECK_V0.1.md`；本机路径只用于追溯，不是 CI 或运行依赖。

GitHub CI、生产镜像、服务器 Native Docker Compose/Caddy 发布和自动部署已经过首页发布验证。PRODUCT-000 的十四个 Grade 页面、两个 Process 页面、Applications、Documents、Markets 与 RFQ 接收端仍是外部依赖；Hub 对这些未就绪路由保持 fail-closed。仓库设置层的分支保护以 GitHub 当前设置为准，工作流中的 main 来源检查持续执行。
