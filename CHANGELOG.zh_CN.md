# 更新日志

本项目所有的重要变更将会记录在此文件中。

日志格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，
并且本项目遵循 [语义化版本号 (Semantic Versioning)](https://semver.org/spec/v2.0.0.html)。

---

## [Unreleased]

### ✨ 新增
- 正式接入官方 `laravel/octane`，并以 `RoadRunner` 作为默认运行时路径。
- 新增已提交的 `config/octane.php`，以及 `octane:install / octane:start / octane:reload / octane:stop` Composer 快捷脚本。

### 🔧 调整
- 将自定义的请求级状态重置逻辑迁移到官方 Octane listener 流程中。
- 明确区分“仓库内已提交的 Octane 配置”与“本机需要单独初始化的 RoadRunner 二进制”。

## [1.1.0] - 2026-03-03

### ✨ 新增
- 新增租户作用域下的 **组织（Organization）** 与 **团队（Team）** 模块，覆盖 CRUD API、DTO、请求校验、策略、服务与资源输出。
- 用户创建/编辑新增 `organizationId` 与 `teamId` 绑定能力，并增加后端一致性校验。
- 新增数据库结构：`organizations`、`teams`，以及用户关联字段 `organization_id`、`team_id`。

### 🔧 调整
- 菜单元数据与路由规则加入 Organization/Team，并按租户作用域严格控制可见性。
- 默认种子数据新增组织/团队与用户关联关系，开箱即用更接近真实租户模型。
- 为发版门禁同步更新 API 契约快照与静态分析基线。

### 🐞 修复
- 修复组织/团队绑定扩展后创建用户 DTO 入参数量不匹配问题。
- 修复前后端契约漂移：`/role/all` 的 `manageableOnly` 查询与新增 Organization/Team API 面定义。

## [1.0.0] - 2026-02-23

### 🎉 首次公开发布 (Obsidian Admin Laravel)

欢迎使用 **Obsidian Admin Laravel** 的首个正式版本。这是一个专为构建强类型、高性能的单体架构(Monolith)打造的企业级后端 API 模板。本项目打破了传统臃肿的 MVC 模式，在架构的最底层直接引入领域驱动设计 (DDD)、严苛的物理隔离边界以及全自动的 OpenAPI 生态。

### ✨ 特性 (Features)
- **核心框架**：Laravel 12 (PHP 8.4)。
- **高并发友好**：默认代码结构已针对 **Laravel Octane** 的长驻 worker 约束做兼容处理，团队可按需接入官方 Octane 包。
- **领域驱动设计 (DDD)**：完全摒弃了传统的全局 `app/Http/Controllers` 目录。将核心逻辑深度重构拆分为高内聚的 `app/Domains` (例如 Auth, Tenant, System 领域)。
- **物理架构守卫 (Deptrac)**：深度集成 `qossmic/deptrac`，在 CI/CD 中通过物理规则强行约束领域之间的互相调用。彻底杜绝未经授权的跨领域“意大利面条”代码依赖。
- **严格的数据传输对象 (DTOs)**：抛弃了不可靠的纯数组(Array)数据传递机制。引入了原生的 PHP 8.2 Readonly DTO 雷厉风行地规范 Controller 与底层 Service 层间的入参，保障绝对的类型安全。
- **无须手写的 OpenAPI 规范**：使用 `dedoc/scramble`，通过静态分析代码树(AST)自动生成完美的 `docs/openapi.yaml`。无需再写哪怕一行冗长恼人的 `@OA` 注释代码。
- **原生多租户架构 (Multi-Tenancy)**：借助底层的 Global Scopes 实现多租户SaaS模型的数据物理/逻辑隔离机制。与总控平台端的数据互不干扰。
- **事件驱动审计日志 (Event-Driven Logs)**：构建了高度可扩展的审计模块。核心的日志记录行为被重构下推至底层消息队列 (Laravel Horizon / Redis)。在高频操作下 API 接口不再被重耗时的日志落库动作拖慢。
- **实时通信架构 (WebSockets)**：原生配置好了最新的 `laravel/reverb` 服务器，并通过统一的 `SystemRealtimeUpdated` 事件广播，直接支持前端无刷新的热更新操作。
- **由 Schema 驱动的前端响应 (Schema Controller)**：内置了一个创新的 `CrudSchemaController`，能够将后端的模型与验证规则直接通过 JSON Schema 的形式投射给前端系统，进而动态渲染出表单和表格组件。
- **坚若磐石的测试用例**：通过 `pestphp/pest` 编写了海量覆盖率极高的功能、单元与架构规范测试断言。

*本项目为构建可无止境横向扩张(Scale-out)的 Laravel 单体护航。*
