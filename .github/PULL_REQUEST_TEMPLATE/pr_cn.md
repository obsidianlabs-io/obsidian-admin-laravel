首先，感谢你的贡献。

这个仓库是生产级 Laravel 后端基线。请尽量让 PR 保持范围单一、变更明确、技术上可辩护。

[[English Template / 英文模板](./pr_en.md)]

### 变更类型

- [ ] 新功能
- [ ] Bug 修复
- [ ] 重构
- [ ] 文档更新
- [ ] CI / 工具链调整
- [ ] 性能 / 运行时调整
- [ ] 安全加固
- [ ] 契约 / OpenAPI 变更
- [ ] 其他

### 背景

> 说明原始问题、需求来源或 issue 链接。

### 方案说明

> 描述实现方式，以及重要的架构决策。

### 契约 / 运行时影响

- [ ] 没有 API 契约变化
- [ ] OpenAPI / 响应结构有变化
- [ ] 迁移或种子逻辑有变化
- [ ] 队列 / 缓存 / Redis 行为有变化
- [ ] 租户 / RBAC / 认证行为有变化
- [ ] Octane / RoadRunner / Reverb 行为有变化

> 如果上面有勾选，请补充简短说明。

### 验证情况

- [ ] `vendor/bin/pint --test`
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G`
- [ ] `php artisan test`
- [ ] 需要时已跑相关数据库矩阵测试
- [ ] 需要时已更新 OpenAPI / contract snapshot

### 风险说明

> 说明可能的 break change、回滚风险、升级风险或运行影响。

### 补充信息

> 如有截图、trace、benchmark、后续计划，可补充在这里。
