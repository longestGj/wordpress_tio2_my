# HOME-001 D32 Gate8 接收记录

日期：2026-09-20。接收任务：00首页开发（01a0bca9-1318-7022-aa49-8723850227f7）。

- dispatch_id: HOME-D32-G8-DISPATCH-20260920-01
- handoff_id: HOME-001-D32-G6-HANDOFF-01
- 开发目录：D:/32Wordpress_new
- origin：https://github.com/longestGj/wordpress_tio2_my.git
- 实际起点：main，空仓库，无 HEAD、无用户文件或未提交改动。不是 D16 代码基线。
- 唯一包：D:/23MySec/pages/home/06_handoff/HOME-001_D32_GATE6_HANDOFF_PACKAGE_V0.2.md
- 包 SHA256：EEB70B4D48753BBAF02D9CBEA1B6ADAD65B18D90CD6C6123A0F63C6063065A3A
- 核验附件 SHA256：2C8DC65F51829CB6120E05F4395B2956435DA004EAA043ECED28EDD3FF7F892F
- 批准来源：HOME-001_D32_GATE6_PROJECT_CONTROL_CLOSEOUT_V1.0.md 和 HOME-001_D32_GATE8_AUTHORIZATION_AND_DISPATCH_V1.0.md；包内历史候选状态不重新触发审批。

接受范围：WordPress 直接管理和生成 HOME-001 首页；唯一共享 Header/Footer/Mobile Menu；no_optional_analytics Cookie Settings。固定设计、后台内容可编辑。整页 V1.1 + Hero V1.4、生产 Logo、五节点 Schema、14 个非链接型号、HOME-VU-A01..A12、DEP-01..05 均继承。

环境初检：Windows/PowerShell，Docker Server 29.6.2 可用，Node/Python/Git 可用，无宿主 PHP。将建立本项目专用本地 WordPress/MariaDB 容器和卷，不操作 D16 栈。

开放项：真实 WordPress 制品/内容指纹与 Gate8→9 工具兼容需实测；25 个批准目标的本地可用性需逐项记录；其他页面、法律正文、表单接收、Analytics 启用与发布均不在本批范围。物理设备/读屏及原生缩放未完成前标为 NOT_VERIFIED。

状态：ACCEPTED / GATE8_STARTED。正在核验输入并实施；不是开发完成、Gate9通过或发布就绪。每个独立任务验证后提交一个 commit。
