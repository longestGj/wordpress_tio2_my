# TiO₂ Products 生产部署设计

日期：2026-09-20

状态：用户已于 2026-09-20 确认

仓库：`longestGj/wordpress_tio2_my`

生产域名：`https://tio2products.com/`

生产服务器：`129.146.32.192`，Ubuntu 24.04

## 1. 目标与边界

本设计建立从 GitHub `main` 到生产服务器的全自动发布流程，并用已经合入 `main` 的首页验证构建、发布、健康检查和回滚链路。

成功标准：

- 功能分支经 `develop` 集成测试后，通过 `develop → main` PR 进入正式发布版本。
- `main` 更新后自动测试、构建固定镜像并自动部署，不设置人工批准步骤。
- 生产站通过 `https://tio2products.com/` 提供服务，`www` 永久重定向到根域名。
- 代码、数据库、上传媒体和 TLS 证书有清晰的数据边界；代码更新不覆盖后台内容。
- 发布失败自动恢复上一代码镜像；数据恢复只使用明确选择的备份，不自动覆盖生产数据。
- 当前全站尚未完成，生产站保持 `noindex, nofollow`。全部页面、法律内容和 RFQ 流程完成后另行放开索引。

本阶段不包含站外备份、CDN、Analytics、邮件发送、法律页面补齐或其余 24 个目标页面的开发。服务器本地备份不能应对整台服务器丢失，该风险由用户在当前阶段接受；全站完成后另建固定站外备份。

## 2. 已确认条件

- GitHub 仓库的默认分支为 `main`；开发流为功能分支 → `develop` → 集成测试 → `main`。
- 生产服务器是无现有网站、数据库或容器的全新 Ubuntu 24.04 主机，根磁盘约 48 GiB、内存约 5.8 GiB。
- 当前只监听 SSH 22；80/443 尚未开放。
- `tio2products.com` 和 `www.tio2products.com` 尚未建立有效 A/CNAME 解析。
- 用户选择 Caddy、GitHub Container Registry（GHCR）和 Docker Compose，并授权 `main` 更新后自动上线。
- 用户选择当前只保留服务器本地备份，并确认首阶段保持 `noindex`。

## 3. 系统架构

生产环境由四个职责单一的组件组成：

1. **Caddy**：唯一公网入口，监听 80/443，自动申请及续期证书，执行 HTTP → HTTPS 和 `www` → 根域名重定向，再将请求转发给 WordPress。
2. **WordPress**：运行自定义生产镜像。镜像基于固定摘要的官方 WordPress PHP/Apache 镜像，包含主题、插件、WP-CLI 和发布身份，不包含秘密或生产数据。
3. **MariaDB**：仅加入内部 Docker 网络，不映射主机端口。数据库数据使用固定命名卷持久化。
4. **部署与备份脚本**：在服务器 `/opt/tio2products` 管理版本目录、共享环境文件、备份、当前版本记录和发布锁。

持久化范围：

- MariaDB 数据卷。
- `/var/www/html/wp-content/uploads` 媒体卷。
- Caddy data/config 卷，保存 ACME 账号和证书状态。
- `/opt/tio2products/shared/.env`、备份及发布状态文件。

WordPress 核心、主题、插件和 WP-CLI 属于镜像，不放进持久卷。容器重建时重新从指定镜像产生，避免旧卷中的代码遮蔽新版本。只有上传目录单独挂载持久卷。

## 4. 镜像和发布身份

生产镜像名称为 `ghcr.io/longestgj/wordpress_tio2_my:<full-commit-sha>`。每个镜像至少包含：

- OCI revision/source/created 标签。
- 完整 Git commit SHA。
- 主题和内容插件的实际文件。
- 与镜像兼容的 WP-CLI。
- 不写入密码、令牌、数据库、媒体或批准材料。

镜像使用完整 commit SHA 发布，部署文件不引用 `latest`。可以附加 `production` 便捷标签，但服务器的生效版本只能读取不可变 SHA 标签。GHCR 镜像设为公开，因为源代码仓库公开且镜像不含秘密；这样生产服务器无需长期保存 registry 凭据。首次镜像建立后，在启用自动生产部署前完成一次 GHCR 可见性配置。

WordPress 响应保留站点 scope，并增加由环境注入的非秘密发布 SHA 响应标记。镜像 OCI 标签、服务器当前版本记录和响应标记必须一致。

## 5. GitHub Actions 流程

建立两类工作流：

### 5.1 Pull Request / develop 验证

- PHP 语法和纯内容校验。
- 可移植的 WordPress/MariaDB 临时环境启动。
- HTTP、SEO、Schema、内容与权限契约。
- Playwright 布局、交互、无障碍自动扫描和后台编辑后精确恢复。
- 测试输出写入运行时临时目录，不覆盖已经提交的历史验收证据。

会改数据库的测试只在该工作流创建的隔离环境中运行。生产环境不运行编辑、错误 scope、缺失内容或外来媒体等破坏性测试。

### 5.2 main 自动发布

触发条件是 `main` 的 push。顺序固定为：

1. 对目标 commit 运行完整可移植测试。
2. 构建 commit SHA 镜像。
3. 使用 GitHub 提供的短期 `GITHUB_TOKEN` 推送 GHCR。
4. 上传该 commit 对应的版本化生产 Compose、Caddyfile 和部署脚本。
5. 使用生产 SSH 密钥连接专用 `deploy` 用户。
6. 在服务器获取排他发布锁、执行发布前备份、拉取指定 SHA 镜像并更新服务。
7. 执行内部与公网健康检查。
8. 成功后记录生效 SHA；失败则恢复上一镜像并再次检查。

GitHub Actions 使用 `concurrency` 对生产环境串行化，不取消已经进入部署阶段的任务。后续提交排队，防止两个发布同时修改服务器。

`main` 设置分支保护：只接受 `develop → main` PR，要求规定检查成功并禁止强推。因为用户选择全自动上线，合并到 `main` 本身就是发布授权。

## 6. 服务器权限与目录

首次配置由当前 root Shell 完成，之后流水线不使用 root：

- 建立无密码登录的 `deploy` 用户。
- 为其安装项目专用 SSH 公钥，并在 GitHub 保存对应私钥为生产环境 secret。
- `deploy` 用户拥有 `/opt/tio2products`，并能够运行本项目所需 Docker 命令。Docker 权限等价于高权限，服务器不承载不受信任的其他租户。
- SSH 使用固定服务器 host key，GitHub Actions 通过预存 known-hosts 验证，不使用 `StrictHostKeyChecking=no`。
- 验证 `deploy` 登录成功后，再决定是否关闭 root 的远程 SSH 登录；不在首次配置中提前锁死现有入口。

目录结构：

```text
/opt/tio2products/
  releases/<commit-sha>/     # 版本化 Compose、Caddyfile、脚本和非秘密发布信息
  current -> releases/...    # 当前部署配置软链接
  previous -> releases/...   # 上一部署配置软链接
  shared/.env                # 生产秘密，0600，不进入 Git
  shared/state/              # 当前/上一 SHA、锁和初始化状态
  backups/<timestamp>/       # 数据库、媒体、元数据和校验值
  logs/                      # 必要的部署记录，不包含秘密
```

Compose 设置固定项目名和固定卷名，使配置目录切换不会创建另一套数据库或媒体。

## 7. 秘密与配置

GitHub Secrets 仅保存：

- `PROD_SSH_PRIVATE_KEY`
- `PROD_KNOWN_HOSTS`
- 必要时将主机、端口和用户作为 GitHub Variables；它们不是业务秘密。

服务器 `shared/.env` 保存：

- MariaDB root 与 WordPress 数据库密码。
- 首次安装使用的 WordPress 管理员用户名、密码和邮箱。
- WordPress salts/keys 或生成它们所需的最终值。
- 正式站点地址、scope 和其他运行配置。

工作流不读取 WordPress 管理员密码或数据库密码。脚本不使用 `set -x`，不把 `.env`、SQL、媒体包或秘密输出到 Actions 日志。

## 8. 首次初始化与后续更新

首次部署脚本必须幂等：

1. 启动 MariaDB 和 WordPress，等待数据库与 HTTP 内部健康。
2. 如果 WordPress 尚未安装，使用服务器 `.env` 的管理员信息执行 core install。
3. 设置 `home`、`siteurl` 为 `https://tio2products.com`，时区、固定链接和 `blog_public=0`。
4. 激活 `tio2-malaysia` 主题和 `tio2-content` 插件。
5. 只有在站点内容 option 不存在时，导入批准首页内容和英雄图；导入后记录内容初始化版本。
6. 如果 WordPress 或内容已经存在，则跳过相应初始化，不删除页面、不重新导入、不覆盖后台内容和媒体。

后续发布只替换镜像并执行明确的兼容检查。任何未来字段或数据库结构变化必须提供幂等迁移、前置备份、版本判断和恢复说明，不能重新运行初始导入代替迁移。

## 9. 域名、HTTPS 与 SEO

外部前置条件：

- 阿里云 DNS：根域名 A 记录指向 `129.146.32.192`；`www` 使用 CNAME 指向根域名或 A 记录指向同一 IP。
- Oracle Cloud 网络安全规则允许公网 TCP 80/443；SSH 22 保留受控访问。
- 主机防火墙只允许 22、80、443，并在启用前确认 SSH 会话不被阻断。

Caddy 配置：

- `tio2products.com` 反向代理到内部 WordPress。
- `www.tio2products.com` 返回 301 到对应根域名路径。
- HTTP 自动跳转 HTTPS。
- 证书目录持久化。
- 添加基础安全响应头，并在实际 WordPress 前台与后台验证兼容性后生效。

主题中的 canonical、Open Graph URL 和 JSON-LD ID 不再硬编码旧域名，统一由正式站点地址安全生成。首阶段 `blog_public=0`，首页输出 `noindex, nofollow`。全部页面和业务流程完成后，另立发布变更将 `blog_public` 改为 1，并验证 sitemap、canonical、robots 和全站目标；普通代码发布不会自动开放索引。

## 10. 备份、回滚与保留

每次部署前生成：

- 一致性 MariaDB dump。
- 上传媒体归档。
- 当前镜像 SHA、数据库/媒体校验信息和备份时间。

每日定时执行同类本地备份。保留最近 7 份成功备份，删除时只处理 `/opt/tio2products/backups` 内已验证命名的目录。备份完成后检查文件非空、SQL 可读、媒体归档可列出并写入 SHA-256。

代码回滚：部署健康检查失败时，将 `current` 和镜像恢复到 `previous`，重新创建 WordPress/Caddy 服务，再次执行健康检查。MariaDB 和媒体卷不随代码回滚而删除或替换。

数据恢复：不自动执行。需要恢复时先停止写入，保留当前故障快照，选择明确的备份目录，验证校验值，再按恢复运行手册操作。首阶段没有站外副本，服务器整体丢失将导致本地备份不可用。

## 11. 健康检查、日志与重启

部署成功条件全部满足才更新生效状态：

- Compose 配置可解析，所有必要容器处于 healthy/running。
- MariaDB 可连接，WordPress 能读取 `tio2-my` 内容。
- 内部 WordPress 返回 200。
- `http://tio2products.com/` 跳转到 HTTPS。
- `https://www.tio2products.com/...` 跳转到相同根域名路径。
- `https://tio2products.com/` 返回 200、正确首页关键内容、正确 canonical、`noindex, nofollow`、正确 scope 和目标 commit SHA。
- TLS 证书主机名和有效期正确。
- 管理登录页可访问；健康检查不执行登录或修改数据。

Docker 服务设置合理的重启策略。Docker JSON 日志配置大小与文件数上限；Caddy 访问/错误日志不记录请求体或秘密。备份、磁盘占用、证书、容器重启次数和发布失败在后续运维阶段接入通知；首轮至少提供可执行的状态检查命令和故障日志位置。

## 12. 实施顺序

1. 从最新 `develop` 创建部署功能分支。
2. 参数化正式 URL 和发布身份，补充测试。
3. 建立生产 Dockerfile、Compose、Caddyfile、初始化/备份/部署/回滚脚本。
4. 使现有测试在 GitHub Actions 干净环境可运行，不覆盖历史证据。
5. 建立 PR 检查和 main 自动发布工作流；使用 SHA 固定第三方 Actions。
6. 在独立本地环境验证首次初始化、重复部署、内容保留、失败回滚和备份可读。
7. 经代码审查后合入 `develop`，在 develop 执行集成测试。
8. 配置服务器 deploy 用户、Docker、目录和服务器秘密。
9. 配置 Oracle 80/443 和阿里云 DNS，核验权威 DNS。
10. 将 develop 合入 main，触发首次自动生产部署。
11. 从公网执行部署后检查、重启恢复检查和一次代码版本回滚演练；恢复目标 main 版本。
12. 保存发布证据，保持 `noindex`，记录 24 个未完成目标和站外备份开放项。

## 13. 验收标准

- GitHub 对 PR 和 main 运行的实际工作流均成功，并能阻止失败版本发布。
- GHCR 存在绑定 main commit 的无秘密镜像，服务器运行相同 SHA。
- 全新服务器能够由版本化配置完成可重复的首次初始化。
- 正式域名 HTTPS、重定向、首页、后台入口、canonical 和 `noindex` 符合设计。
- 后台内容和上传媒体在同版本重发、升级发布、容器重建及代码回滚后保持不变。
- 发布前和每日本地备份可验证读取，保留策略只影响指定备份目录。
- 故意部署一个健康检查不通过的候选时，自动恢复上一镜像并保持数据库和媒体不变；该演练仅在受控候选中进行。
- 服务器重启后服务恢复，线上版本、数据和 HTTPS 身份不漂移。
- Git 历史遵循功能分支 → develop → main，提交、镜像、部署状态和生产响应可追溯。
- 没有密码、私钥、令牌、SQL dump 或媒体备份进入 Git、GHCR 镜像或公开日志。
