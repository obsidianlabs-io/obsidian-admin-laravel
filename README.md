<div align="center">
	<img src="./public/favicon.svg" width="160" />
	<h1>Obsidian Admin Laravel</h1>
  <span>中文 | <a href="./README.en_US.md">English</a></span>
</div>

---

[![license](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)

> [!NOTE]
> `Obsidian Admin Laravel` 是一个健壮的、生产可用的企业级后端模板，专为标准 Vue3/React 管理后台（如 Obsidian Admin Vue）提供可靠的 API 基础服务。

## 简介

[`Obsidian Admin Laravel`](https://github.com/obsidianlabs-io/obsidian-admin-laravel) 是一个基于 **Laravel 12** 构建的具有高度结构化、可扩展和安全的后端模板。与传统臃肿的单体应用不同，本项目严格执行 **Clean Architecture (整洁架构)** 模式，将业务逻辑下沉到专门的 Service 层，并使用 **Data Transfer Objects (DTOs)** 保证严格的类型安全。它原生支持真正的多租户架构、企业级基于角色的访问控制 (RBAC)、内置审计日志，并支持使用 **Laravel Octane (RoadRunner)** 提供极致的高并发运行环境。

## 创始愿景

Obsidian 由 **Boss · Beyond · Black** 创立 —— 三股独特的力量因同一个愿景而凝聚在一起。

**Boss** 象征着卓越的领导力与严谨的体系架构。
**Beyond** 代表着无尽的创新与打破界限的勇气。
**Black** 意味着极致的深度、精准与战略性的清晰度。

尽管征途各自展开，我们共同铸就的基石恒久如初。

Obsidian 持续进化 —— 扎根韧性与秩序，坚定迈向长期价值。

## 特性

- **前沿技术栈**：基于 PHP 8.2+, Laravel 12, PostgreSQL 和 Redis。
- **极致性能**：预配置支持 **Laravel Octane (基于 RoadRunner)**，为高并发提供企业级的运行速度和异步处理能力。
- **整洁架构**：严格遵循 `Controller -> DTO -> Service -> Model` 的设计模式。告别“臃肿的控制器”。
- **严格的数据传输对象 (DTO)**：内置 `DTO` 模式，在 HTTP 请求和底层业务服务之间强制执行严格的类型检查。
- **真正的多租户架构**：内置对全局平台和隔离租户边界的支持，并实现清晰的租户切换逻辑。
- **企业级 RBAC**：开箱即用的、坚若磐石的基于角色访问控制。与后端中间件及前端的动态路由解析完美集成。
- **全局异常处理**：全局统一拦截异常，提供美观、可预测的标准化 JSON 错误响应，避免 API 返回未处理的 HTML 异常页面。
- **全面的审计日志**：可扩展的审计日志实现，精细追踪跨平台和租户范围的用户操作。
- **详尽的测试覆盖**：包含功能完备的 Pest/PHPUnit 测试套件，确保架构完整性和系统安全性。

## 生态系统

此后端专为与以下前端完美配合而设计：
- **[Obsidian Admin Vue](https://github.com/obsidianlabs-io/obsidian-admin-vue)**: 一个清新、优雅的管理后台UI，基于 Vue3, Vite, NaiveUI 和 TypeScript (衍生自 Soybean Admin)。

## 版本

- **Laravel 版本**: 12.x
- **PHP 版本**: 8.2+

## 使用

**环境准备**

确保你的环境满足以下要求：
- **PHP**: >= 8.2
- **Composer**: >= 2.x
- **Database**: PostgreSQL 14+
- **Cache**: Redis 6+

**克隆项目**

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
```

**运行与部署 (两种方式)**

您可以根据自身的技术栈选择使用传统的 PHP 原生方式进行本地开发，或者使用完全配置好的 Docker Compose 进行生产级部署。

### 方式一：本地 PHP 原生开发

如果您在本地安装了 `php` 和 `composer`，可以使用传统的 Artisan 命令启动：

```bash
# 1. 复制环境变量文件
cp .env.example .env

# 2. 安装 Composer 依赖
cd obsidian-admin-laravel
composer install

# 3. 生成应用密钥
php artisan key:generate

# 4. 运行数据库迁移和填充 (确保您的本地已存在 MySQL 和 Redis)
php artisan migrate --seed

# 5. 启动开发服务器
php artisan serve

# 或使用 RoadRunner/Octane 以获得极致性能
php artisan octane:start
```

**运行测试用例**

```bash
composer run test
```

### 方式二：Docker 容器化生产级部署

项目内置了极为完整的 Docker Compose 配置，包含 `PHP-FPM`、`Nginx`、`MySQL`、`Redis`、`Horizon` 队列监听器等所有生产级高可用服务。

**1. 一键启动所有服务**

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
| `nginx` | Web 服务器 / 反向代理 | **8080** |
| `mysql` | MySQL 8.0 数据库 | 3306 |
| `redis` | Redis 7 缓存与队列 | 6379 |
| `horizon` | Laravel Horizon 队列管理 | - |
| `scheduler` | Laravel 任务调度器 | - |
| `pulse-worker` | Laravel Pulse 监控Worker | - |
| `reverb` | Laravel Reverb WebSocket 服务器 | 6001 |

## 鸣谢

Obsidian Admin Laravel 架构的灵感来源于一系列卓越的开源项目和架构思想，特别感谢 **[DTO](https://github.com/spatie/data-transfer-object)** (Spatie) 和现代化单体架构 (Modular Monolith) 的布道者们，让本项目从 Controller 乱象中重生。

## 开源协议

本项目基于开源 [MIT License](./LICENSE) 协议发布。

*Copyright © 2026 Obsidian Labs.*
