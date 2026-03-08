<div align="center">
	<img src="./public/favicon.svg" width="160" />
	<h1>Obsidian Admin Laravel</h1>
  <span><a href="./README.md">English</a> | 中文</span>
</div>

---

[![license](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)

> [!NOTE]
> `Obsidian Admin Laravel` 是一个严格类型化、生产可用、面向企业后台与 SaaS 平台的 Laravel 12 API 基线。它专为标准 Vue3/React 管理后台（如 Obsidian Admin Vue）提供可靠、可扩展、可长期维护的后端能力。
>
> 当前仓库已经包含官方 `Laravel Octane` 集成，并默认面向 `RoadRunner` 运行时。需要注意的是，RoadRunner 二进制仍然是机器级文件，需要在本地初始化，因此在使用前请先阅读 [`docs/octane.md`](./docs/octane.md)。

## 核心定位

- **企业后台 API 基线**：适合作为管理后台、运营后台、内控平台、SaaS 控制台的后端起点。
- **严格边界而不是传统胖 MVC**：通过 `DTO / Action / Service / Result Data` 降低无结构数组与隐式耦合。
- **多租户优先**：内建租户上下文、租户安全边界、作用域切换与跨租户防泄漏约束。
- **适合长期演进**：内置 OpenAPI、审计、幂等、健康检查、CI 质量门禁与架构测试。
- **高并发友好**：项目已正式接入 `Laravel Octane`，并针对长驻 worker 的状态隔离做了额外处理。

## 快速开始（先看这里）

如果你只是想先把 API 跑起来，先选下面一种方式即可：

## 关键文档

- 版本兼容矩阵：[`docs/compatibility-matrix.md`](./docs/compatibility-matrix.md)
- Octane / RoadRunner 运行说明：[`docs/octane.md`](./docs/octane.md)
- 生产运行栈：[`docs/production-runtime.md`](./docs/production-runtime.md)
- 发布签发清单：[`docs/release-final-checklist.md`](./docs/release-final-checklist.md)

### 方式 A：Docker 开发环境（推荐，最省环境配置）

适合第一次运行项目、团队统一环境、或你想直接使用 `MySQL + Redis + Horizon + Reverb` 完整栈进行本地开发。

> [!TIP]
> 如果你是 **Windows 原生环境**，推荐优先使用 Docker Desktop（或 WSL2）运行本项目。`Laravel Horizon` 依赖 `pcntl/posix`，Windows 原生 PHP 环境通常无法安装/运行。

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

# 启动开发环境完整栈
# one-shot composer 服务会先自动安装 vendor，再启动 app。
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

健康检查：

```bash
curl http://localhost:8080/api/health
```

> [!NOTE]
> 为了避免 Windows 下 `storage/logs/laravel.log` 偶发权限问题，开发环境已做两点处理：
> 1) 容器日志默认输出到 `stderr`（使用 `docker logs` 查看）
> 2) `storage` 与 `bootstrap/cache` 使用独立 Docker volume（不直接落在 Windows 挂载目录）
>
> 另外，开发栈现在使用 one-shot `composer` 服务，并与应用镜像共享同一套 PHP 扩展基线，因此不会再出现 RoadRunner/Octane 依赖（例如 `ext-sockets`）只在某个阶段缺失的问题。
>
> 如果你之前已启动过旧容器并遇到日志权限报错，可重建开发栈（会清空本地开发数据）：
> ```bash
> docker compose -f docker-compose.dev.yml down -v
> docker compose -f docker-compose.dev.yml up -d --build
> docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
> ```
>
> 如果只是 `composer.json` 或 `composer.lock` 有变化，且你只想刷新依赖而不重建整个开发栈：
> ```bash
> docker compose -f docker-compose.dev.yml run --rm composer
> ```

### 方式 B：本地 PHP 原生开发（已有 PHP/Composer 环境）

适合日常调试、断点开发、快速改代码。你可以使用本地 `MySQL + Redis`，也可以自行改成 `sqlite`。

> [!WARNING]
> **Windows 原生 PHP 环境**通常无法安装/运行 `Laravel Horizon`（依赖 `pcntl/posix`）。如果你不使用 Docker/WSL2，请使用 `php artisan queue:work` 作为本地队列 worker 替代，不要在 Windows 原生环境尝试运行 `php artisan horizon`。

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

运行测试：

```bash
composer run test
# 使用 MySQL 配置执行测试
composer run test:mysql
# 使用 PostgreSQL 配置执行测试
composer run test:pgsql
```

> [!TIP]
> 当前 `.env.example` 默认值面向 Docker（`MySQL + Redis`）。如果你走本地原生开发且不想启动 MySQL/Redis，请按需改 `.env`（例如改用 `sqlite`）。
>
> Windows 原生开发建议同时调整：
> ```env
> # 本地先用同步审计，避免依赖 Horizon
> AUDIT_QUEUE_CONNECTION=sync
> # 或保留队列，但用 queue:work 而不是 horizon
> # QUEUE_CONNECTION=database
> ```

## 简介

[`Obsidian Admin Laravel`](https://github.com/obsidianlabs-io/obsidian-admin-laravel) 是一个基于 **Laravel 12** 构建的、高度结构化且面向生产环境的后端模板。它不是传统“先堆功能、后补规范”的后台项目，而是从一开始就以 **清晰边界、严格类型、安全治理、多租户隔离、长期维护成本** 为核心目标来设计。

项目采用模块化单体（Modular Monolith）结构，将业务拆分到 `app/Domains/*` 中，并通过 **DTO、Query Action、Result/Data Object、架构测试与静态分析** 收紧系统边界。对于需要承接企业后台、内部平台、SaaS 控制台、配置中心、审计中心的团队，它提供的是一个可以持续扩展、而不是很快失控的 Laravel 基线。

## 创始愿景

Obsidian 由 **Boss · Beyond · Black** 创立 —— 三股独特的力量因同一个愿景而凝聚在一起。

**Boss** 象征着卓越的领导力与严谨的体系架构。
**Beyond** 代表着无尽的创新与打破界限的勇气。
**Black** 意味着极致的深度、精准与战略性的清晰度。

尽管征途各自展开，我们共同铸就的基石恒久如初。

Obsidian 持续进化 —— 扎根韧性与秩序，坚定迈向长期价值。

## 特性

### 架构与领域设计

- **Laravel 12 + PHP 8.4+**：支持 `MySQL / PostgreSQL / SQLite`，默认适配 `Redis` 缓存与队列。
- **模块化单体（Modular Monolith）**：按 `app/Domains/*` 组织业务（如 `Auth / Access / Tenant / System / Shared`），便于长期演进。
- **清晰分层**：遵循 `Controller -> DTO -> Service -> Model` 的职责划分，减少“胖控制器”。
- **DTO 模式**：关键写操作通过 DTO 承载输入，降低无结构数组在业务层蔓延的风险。
- **动态 CRUD Schema API**：后端可输出页面 schema / 表格 schema，支撑前端配置化页面能力。

### 多租户与权限控制

- **平台 / 租户双作用域**：支持 `No Tenant` 平台模式与租户内作用域切换。
- **租户上下文解析**：统一处理 `X-Tenant-Id`、超级管理员租户切换、平台作用域限制。
- **租户安全边界**：包含后端作用域约束、数据库约束与跨租户越权测试覆盖。
- **Tenant -> Organization -> Team 结构能力**：支持租户下组织与团队管理，用户可绑定组织/团队，且后端强校验团队归属组织与租户一致性。
- **RBAC（单用户单角色）**：用户绑定单角色，角色绑定权限，前后端联动控制菜单与接口访问。
- **角色等级（Role Level）治理**：支持角色层级管理（如超级管理员 / 管理员 / 用户），限制同级与越级操作。
- **权限分组自动化**：权限分组可由权限码前缀推导（例如 `permission.view` -> `permission`）。

### 认证与安全能力

- **Sanctum Token 双令牌机制**：Access Token + Refresh Token，支持 Remember Me 会话时长。
- **多端会话管理**：支持会话列表、设备别名、会话撤销（当前会话与其他会话）。
- **单设备登录策略开关**：可按项目配置是否启用“登录即踢下其他设备”。
- **TOTP 双重验证（2FA）**：支持管理员 2FA 流程，并包含 **TOTP 防重放保护**（Replay Protection）。
- **登录限流与密码策略**：登录限流、强密码规则、可配置的安全基线阈值。
- **统一 API 错误包装**：标准化 JSON 响应（含 `requestId` / `traceId`），避免裸异常页面。
- **安全基线检查命令**：内置 `security:baseline`，可用于 CI 安全策略门禁。

### 审计、配置与平台治理

- **审计日志（Audit Logs）**：覆盖平台与租户范围的关键行为记录。
- **审计策略（Audit Policy）**：支持按动作配置启用/禁用、采样率、保留天数，并记录变更历史。
- **异步审计写入**：审计日志可通过队列异步写入（支持 `Redis/Horizon`），提升 API 响应性能。
- **功能开关（Feature Flags）**：支持菜单/功能开关与灰度放量比例。
- **语言管理（Language）**：支持多语言字典内容管理与运行时国际化配置。
- **主题配置（Theme Config）**：提供平台级主题配置接口，供管理前端动态读取。
- **项目配置模板（Project Profile）**：支持按预设快速应用一组安全/功能/审计策略配置。

### 实时、性能与可观测性

- **官方 Laravel Octane 集成**：默认以 `RoadRunner` 为主路径，并已处理请求级状态泄漏风险。
- **Laravel Reverb / WebSocket**：预置实时广播基础设施，可用于系统通知与实时刷新。
- **Horizon / Pulse 集成**：支持队列监控与运行时指标观测（依赖部署环境配置）。
- **健康检查接口**：提供 `/api/health`、`/api/health/live`、`/api/health/ready`。
- **链路追踪标识**：支持 `traceparent` 透传，并在响应中统一返回 `traceId`。
- **幂等与乐观锁支持**：关键写接口支持幂等键与可选乐观锁控制，降低重复提交风险。
- **代理可信链治理**：支持 `TRUSTED_PROXIES / TRUSTED_PROXY_HEADERS` 配置与自检命令。

### 工程化与质量保障

- **Pest / PHPUnit 测试体系**：覆盖功能测试、回归测试、架构测试与命令测试。
- **Larastan / PHPStan 静态分析**：提升类型安全与重构稳定性。
- **Laravel Pint 代码风格**：统一格式规范，便于团队协作。
- **Deptrac 领域边界约束**：防止跨模块随意依赖，守住模块化单体边界。
- **OpenAPI 自动生成与校验**：基于 `dedoc/scramble` 输出文档，并支持 OpenAPI lint / 契约快照校验。
- **CycloneDX SBOM 生成 + Attestation**：在 CI 中输出可复现、可验证来源的运行时依赖清单。
- **CI Quality Gate**：可在 CI 中执行测试、静态分析、风格检查、代理配置检查、安全基线检查。

## 生态系统

此后端专为与以下前端完美配合而设计：
- **[Obsidian Admin Vue](https://github.com/obsidianlabs-io/obsidian-admin-vue)**: 一个清新、优雅的管理后台UI，基于 Vue3, Vite, NaiveUI 和 TypeScript (衍生自 Soybean Admin)。

## 使用

**环境准备**

确保你的环境满足以下要求：
- **PHP**: >= 8.4
- **Composer**: >= 2.x
- **Database**: MySQL 8+ / PostgreSQL 14+ / SQLite（本地可用）
- **Cache**: Redis 6+

**克隆项目**

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
```

**运行与部署（两种方式，二选一）**

您可以根据自身的技术栈选择使用传统的 PHP 原生方式进行本地开发，或者使用完全配置好的 Docker Compose 进行生产级部署。

### 方式一：本地 PHP 原生开发

如果您在本地安装了 `php` 和 `composer`，可以使用传统的 Artisan 命令启动。

> [!TIP]
> `.env.example` 默认是 Docker 友好的 `MySQL + Redis` 配置；如果你本地没有这些服务，请先调整 `.env` 后再执行迁移。

```bash
# 1. 进入项目目录并复制环境变量文件
cd obsidian-admin-laravel
cp .env.example .env

# 2. 安装 Composer 依赖
composer install

# 3. 生成应用密钥
php artisan key:generate

# 4. 运行数据库迁移和填充（确保你的数据库配置可用）
php artisan migrate --seed

# 5. 启动开发服务器
php artisan serve

# 如需启用本机的 RoadRunner 二进制并运行 Octane
php artisan octane:install --server=roadrunner
php artisan octane:start --server=roadrunner
```

关于当前仓库的 Octane 支持模型、本地二进制初始化方式以及生产建议，请查看 [`docs/octane.md`](./docs/octane.md)。

**运行测试用例**

```bash
composer run test
composer run test:mysql
composer run test:pgsql
```

### 方式二：Docker 容器化生产级部署

项目内置了生产 Compose 配置，采用镜像化部署方式（不挂载宿主机源码目录），包含 `PHP-FPM`、`Nginx`、`MySQL`、`Redis`、`Horizon` 队列监听器等服务。

**1. 一键启动所有服务（生产镜像化部署）**

```bash
docker compose -f docker-compose.production.yml up -d --build
```

**2. 首次启动后加载数据**

> [!IMPORTANT]
> 每次使用 `down -v` 清空数据卷后重启，都需要重新执行此命令。

```bash
docker exec obsidian-admin-laravel-app-1 php artisan migrate --force --seed
```

**确保 `.env` 队列和缓存配置正确**

> [!WARNING]
> 项目使用 Laravel Horizon 处理异步队列任务（如审计日志）。Horizon **仅支持 Redis 驱动**，如果设置为 `database` 则队列任务不会被自动处理。
>
> 请确保 `.env` 中的配置如下：
> ```env
> QUEUE_CONNECTION=redis
> CACHE_STORE=redis
> ```
> 修改 `.env` 后，必须使用 `--force-recreate` 重建容器才能生效（普通 `restart` 不会重新加载环境变量）：
> ```bash
> docker compose -f docker-compose.production.yml up -d --force-recreate
> ```

**验证服务健康状态**

```bash
curl http://localhost:8080/api/health
```

返回 `"status": "ok"` 表示所有服务运行正常。

**停止服务**

```bash
# 仅停止服务（保留数据卷）
docker compose -f docker-compose.production.yml down

# 停止并清空所有数据卷（慎用）
docker compose -f docker-compose.production.yml down -v
```

| 服务 | 说明 | 映射端口 |
|---|---|---|
| `app` | PHP-FPM 应用容器 | 9000 (内部) |
| `nginx` | Web 服务器 / 反向代理 | **8080**（默认，可通过 `APP_HTTP_PORT` 调整） |
| `mysql` | MySQL 8.0 数据库 | 仅容器内 |
| `redis` | Redis 7 缓存与队列 | 仅容器内 |
| `horizon` | Laravel Horizon 队列管理 | - |
| `scheduler` | Laravel 任务调度器 | - |
| `pulse-worker` | Laravel Pulse 监控Worker | - |
| `reverb` | Laravel Reverb WebSocket 服务器 | 6001（默认，可通过 `REVERB_PUBLIC_PORT` 调整） |

## 常用命令

```bash
# 运行测试（Pest）
composer run test

# 代码格式检查
vendor/bin/pint --test

# 静态分析
vendor/bin/phpstan analyse --memory-limit=1G

# 安全基线检查（严格模式）
php artisan security:baseline --strict

# 代理可信链配置检查（严格模式）
php artisan http:proxy-trust-check --strict

# 生成/校验 OpenAPI 文档（如已启用文档）
php artisan openapi:lint
php artisan api:contract-snapshot --check
```

## 常用入口

- 健康检查：`GET /api/health`
- OpenAPI 文档：`/docs/api`（取决于 `API_DOCS_ENABLED`）
- Horizon 面板：`/horizon`（取决于部署与权限配置）
- Pulse 面板：`/ops/pulse`（取决于部署与权限配置）

## 鸣谢

Obsidian Admin Laravel 架构的灵感来源于一系列卓越的开源项目和架构思想，特别感谢 **[DTO](https://github.com/spatie/data-transfer-object)** (Spatie) 和现代化单体架构 (Modular Monolith) 的布道者们，让本项目从 Controller 乱象中重生。

## 开源协议

本项目基于开源 [MIT License](./LICENSE) 协议发布。

*Copyright © 2026 Obsidian Labs.*


## Octane Runtime

```bash
docker compose -f docker-compose.octane.yml up -d --build
```

该方式会使用仓库内提交的 `.rr.yaml` 作为 RoadRunner 默认模板。该运行方式不会 bind-mount 整个仓库，因此容器会自行下载并使用 Linux 兼容的 `rr` 二进制。
