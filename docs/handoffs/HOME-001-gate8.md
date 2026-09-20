# HOME-001 WordPress Gate8 开发回执

日期：2026-09-20。状态：IMPLEMENTED / READY_FOR_INDEPENDENT_GATE9；不是 Gate9 PASS 或全站发布就绪。

- dispatch_id：HOME-D32-G8-DISPATCH-20260920-01
- handoff_id：HOME-001-D32-G6-HANDOFF-01
- 接收任务：00首页开发，01a0bca9-1318-7022-aa49-8723850227f7
- 来源：D23 HOME-001_D32_GATE6_HANDOFF_PACKAGE_V0.2；包 SHA256 EEB70B4D48753BBAF02D9CBEA1B6ADAD65B18D90CD6C6123A0F63C6063065A3A。
- 仓库：D:/32Wordpress_new；origin https://github.com/longestGj/wordpress_tio2_my.git
- 分支：codex/home-001-wordpress；初始接收提交：61cf09e0adabd90d67af645f973d936de7902d5e。
- 实现提交：a75572a36cc50e820b640fca663a3a60594029cb
- 实际制品：wp-f576fa2d7a74ab057e5878765db4187ae7b2689aaf921f8c5d6f5ab0bac6fc3e
- 恢复后内容 SHA256：ee6a324ea68ada21e9424f930ed52f87800cea9e982c0feb4fd9ef456b71a992
- 预览：http://127.0.0.1:8232/；管理入口：http://127.0.0.1:8232/wp-admin/（凭据仅在本机忽略的.env）。

## 实现及范围

WordPress7.1 / PHP8.3.33，定制主题 tio2-malaysia1.0.0 与内容插件 tio2-content1.0.0。一个主页、唯一共享 Header/Footer/Mobile Menu、未启用可选分析的 Cookie Settings。固定设计下，218 个类型化字段由后台维护；普通文案、链接、图片、型号、SEO 修改不需要改主题或批准编号。运行环境与卷属于 d32-tio2-my。

执行了整页V1.1和HeroV1.4输入映射、完整内容/链接比对、五宽度实际浏览器测试、后台编辑/拒绝/精确恢复、真实错误站点及缺失内容负例。11项浏览器测试、21项PHP内容断言、HTTP/SEO/实际指纹/源比对和所有主题插件PHP语法检查均通过。独立代码审查的首次安装说明问题已修复；具体范围和限制见 code-review.md。

HOME-VU-A01..A12逐项观察与DEP-01..05见 acceptance.md。源48文件哈希均一致；59链接实例和25目标一致，首页200、其余24目标真实404。没有通过占位页面或隐藏链接掩盖依赖。

## 身份适配、恢复与复核

`scripts/artifact.py` 对实际主题/插件全部文件按路径排序，连接 `path + NUL + SHA256(bytes) + LF` 后生成 SHA256；`wp-` 前缀只是类型标识。BUILD_ID 位于实际复制并复核过的 `.runtime/artifacts/<build_id>/`。Git文本过滤可能令Windows工作树字节与提交LF字节不同；脚本另核对过滤后的Git blob身份，制品与运行标记绑定实际工作树字节。不是Next构建，也不伪造Next路径。

本地响应包含实际制品与实际内容SHA256标记及X-Site-Scope。content哈希使用无空白、未转义Unicode/斜线的JSON，与数据库内容及已恢复快照精确一致。仅本地环境输出指纹；生产运行不读取D23。环境版本、镜像摘要、实际媒体哈希和非秘密配置在 environment.json。

恢复步骤见 README.md。content-restored.json 是内容快照，依赖当前数据库/媒体卷；不是全库备份。独立复核前不要编辑内容、切换主题或停止容器。保持运行至 `GATE9_PASS_OR_RETURN_NOTICE`。

最终机器清单：`D:/32Wordpress_new/.runtime/handoff/gate8_evidence_manifest.json`。清单在证据提交后生成，绑定该提交为 evidence_head；为避免清单包含自身提交哈希的循环，它与原工具输出位于忽略的本地交付目录，回执和全部证据文件均已提交。不得因此省略独立验证。

原D23工具输出：`.runtime/handoff/manifest-validation.json`、`.runtime/handoff/gate9-preflight.json`。运行命令：

```powershell
python D:/23MySec/skills/runtime-implementation-verification/scripts/validate_evidence_manifest.py .runtime/handoff/gate8_evidence_manifest.json --output .runtime/handoff/manifest-validation.json
python D:/23MySec/skills/runtime-implementation-verification/scripts/gate9_preflight.py .runtime/handoff/gate8_evidence_manifest.json --rounds 2 --output .runtime/handoff/gate9-preflight.json
```

`require_build_marker=false` 仅关闭Next专属标记检查，contains同时要求精确WordPress制品及内容标记。两轮自预检不等于独立Gate9通过；接收方应在同一机器运行原工具并检查实际页面。

## 未完成的验收及集成项

- HOME-VU-A09：物理读屏、物理触控、原生200%缩放 NOT_VERIFIED；没有声称完整无障碍合规。
- DEP-03：24个后续页面目标由对应页面owner实现，包含法律页、RFQ及文档请求路径；阻断相关集成/发布。
- DEP-04：D23组织独立Gate9；不能用本任务自检或D16历史结果替代。
- DEP-05：全站发布、表单接收、法律页及明确发布授权未在本批关闭。未执行push、merge、生产部署、域名切换、索引或Analytics启用。

## 证据引用

以下路径均相对本仓库，类型与SHA256在机器清单中；临时编辑截图明确标为测试状态。

EVIDENCE: docs/source-map.json
EVIDENCE: docs/verification/home/acceptance.md
EVIDENCE: docs/verification/home/artifact.json
EVIDENCE: docs/verification/home/code-review.md
EVIDENCE: docs/verification/home/content-before.json
EVIDENCE: docs/verification/home/content-restored.json
EVIDENCE: docs/verification/home/cookie-390.png
EVIDENCE: docs/verification/home/dependencies.json
EVIDENCE: docs/verification/home/editor-changed.png
EVIDENCE: docs/verification/home/editor-results.json
EVIDENCE: docs/verification/home/environment.json
EVIDENCE: docs/verification/home/hero-1440.png
EVIDENCE: docs/verification/home/hero-390.png
EVIDENCE: docs/verification/home/hero-768.png
EVIDENCE: docs/verification/home/home-1024.png
EVIDENCE: docs/verification/home/home-1440.png
EVIDENCE: docs/verification/home/home-320.png
EVIDENCE: docs/verification/home/home-390.png
EVIDENCE: docs/verification/home/home-768.png
EVIDENCE: docs/verification/home/homepage-response.html
EVIDENCE: docs/verification/home/identity.json
EVIDENCE: docs/verification/home/layout-1024.json
EVIDENCE: docs/verification/home/layout-1440.json
EVIDENCE: docs/verification/home/layout-320.json
EVIDENCE: docs/verification/home/layout-390.json
EVIDENCE: docs/verification/home/layout-768.json
EVIDENCE: docs/verification/home/menu-390.png
EVIDENCE: docs/verification/home/negative-restore.json
EVIDENCE: docs/verification/home/negative-runtime.json
EVIDENCE: docs/verification/home/privacy.json
EVIDENCE: docs/verification/home/products-expanded-390.png
EVIDENCE: docs/verification/home/source-hashes.json
EVIDENCE: docs/verification/home/source-runtime-parity.json
EVIDENCE: docs/verification/home/test-results.json
