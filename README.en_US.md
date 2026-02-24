<div align="center">
	<img src="./public/favicon.svg" width="160" />
	<h1>Obsidian Admin Laravel</h1>
  <span><a href="./README.md">中文</a> | English</span>
</div>

---

[![license](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)

> [!NOTE]
> `Obsidian Admin Laravel` is a robust, production-ready enterprise backend boilerplate tailored specifically to act as the API foundation for standard Vue3/React admin dashboards (like Obsidian Admin Vue). 

## Quick Start (Start Here)

If you just want to get the API running quickly, choose one of the following:

### Option A: Docker Development (Recommended, least setup friction)

Best for first-time setup, team-standardized environments, or when you want the full `MySQL + Redis + Horizon + Reverb` stack for local development.

> [!TIP]
> If you are on **native Windows**, prefer Docker Desktop (or WSL2). `Laravel Horizon` depends on `pcntl/posix`, which is typically unavailable in native Windows PHP environments.

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

# 1) Prepare vendor first (dev compose bind-mounts source code; vendor is a dedicated volume)
docker compose -f docker-compose.dev.yml run --rm composer

# 2) Start the full development stack
docker compose -f docker-compose.dev.yml up -d --build
docker exec obsidian-admin-laravel-app-1 php artisan key:generate
docker exec obsidian-admin-laravel-app-1 php artisan migrate --force --seed
```

Health check:

```bash
curl http://localhost:8080/api/health
```

### Option B: Native PHP Development (Local)

Best for day-to-day debugging and iterative development. You can use local `MySQL + Redis`, or switch `.env` to `sqlite` for a lightweight local setup.

> [!WARNING]
> **Native Windows PHP** typically cannot run `Laravel Horizon` (missing `pcntl/posix`). If you are not using Docker/WSL2, use `php artisan queue:work` as the local queue worker instead of `php artisan horizon`.

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Run tests:

```bash
composer run test
```

> [!TIP]
> `.env.example` is Docker-first (`MySQL + Redis`) by default. If you want native local development without those services, adjust `.env` first (for example, switch to `sqlite`).
>
> Suggested Windows-native local override:
> ```env
> # Simplest local option for audit logging during development
> AUDIT_QUEUE_CONNECTION=sync
> # Or keep queues enabled and run queue:work instead of horizon
> # QUEUE_CONNECTION=database
> ```

## Introduction

[`Obsidian Admin Laravel`](https://github.com/obsidianlabs-io/obsidian-admin-laravel) is a highly structured, scalable, and secure backend template built on **Laravel 12**. Unlike standard monolithic applications with fat controllers, this project enforces strict **Clean Architecture** patterns, pushing business logic into specialized Services and using **Data Transfer Objects (DTOs)** for strict type safety. It features native true multi-tenancy, enterprise Role-Based Access Control (RBAC), built-in audit logging, and utilizes **Laravel Octane (via RoadRunner)** for ultra-high-performance execution.

## The Vision

Obsidian was founded by **Boss · Beyond · Black** — three distinct forces united by one vision.

**Boss** embodied leadership and structure.
**Beyond** represented innovation and the courage to challenge limits.
**Black** stood for depth, precision, and strategic clarity.

Though our journeys unfold apart, the foundation we forged remains eternal.

Obsidian continues to evolve — rooted in resilience and order, marching steadfast toward enduring value.

## Features

### Architecture & Domain Design

- **Laravel 12 + PHP 8.2+** with support for `MySQL / PostgreSQL / SQLite` and `Redis` for cache/queues.
- **Modular Monolith structure** organized by `app/Domains/*` (e.g. `Auth / Access / Tenant / System / Shared`).
- **Layered design** following `Controller -> DTO -> Service -> Model` to reduce fat controllers.
- **DTO-driven write flows** for safer, more maintainable request-to-domain boundaries.
- **Dynamic CRUD Schema API** to power schema-driven frontend pages (forms/tables/search schemas).

### Multi-Tenancy & Access Control

- **Platform + Tenant dual scope** (`No Tenant` platform scope and tenant-selected scope).
- **Tenant context resolution** with centralized `X-Tenant-Id` handling and super admin tenant switching rules.
- **Tenant safety boundaries** enforced through backend scope checks, database constraints, and cross-tenant tests.
- **RBAC (single user, single role)** with backend permission guards and frontend route/menu integration.
- **Role Level governance** to prevent same-level and higher-level management operations.
- **Permission grouping support** derived from permission code prefixes (e.g. `permission.view` -> `permission`).

### Authentication & Security

- **Sanctum dual-token sessions** (access + refresh tokens) with remember-me TTL support.
- **Multi-device session management** (session list, device alias, session revoke).
- **Single-device login policy toggle** configurable per project.
- **TOTP-based 2FA** with **replay protection** for one-time code reuse prevention.
- **Login rate limiting and password policy** with configurable thresholds.
- **Unified API error wrapper** (including `requestId` and `traceId`) for predictable client behavior.
- **Security baseline checks** via `security:baseline` (CI-friendly policy gate).

### Auditing, Config, and Platform Governance

- **Audit Logs** for platform- and tenant-scoped action tracking.
- **Audit Policy** with per-action enable/disable, sampling rate, retention days, and change history.
- **Queued audit writes** for lower API latency under load (`Redis/Horizon` friendly).
- **Feature Flags** with rollout percentages for gradual menu/feature rollout.
- **Language management** for runtime translation content administration.
- **Theme Config APIs** for platform-level frontend theme configuration.
- **Project Profiles** to apply baseline env defaults and audit policy presets for different deployment styles.

### Realtime, Performance & Observability

- **Octane / RoadRunner compatibility** (including request-state leak safeguards).
- **Laravel Reverb / WebSocket** foundation for realtime notifications and UI refresh.
- **Horizon / Pulse integration** for queue and runtime observability (deployment-dependent).
- **Health endpoints**: `/api/health`, `/api/health/live`, `/api/health/ready`.
- **Tracing identifiers** with `traceparent` propagation and response `traceId`.
- **Idempotency and optimistic-lock support** for safer write APIs.
- **Trusted proxy configuration + validation command** for correct client IP handling behind reverse proxies.

### Engineering & Quality Gates

- **Pest / PHPUnit** coverage across feature, regression, command, and architecture tests.
- **Larastan / PHPStan** static analysis for stronger typing and safer refactors.
- **Laravel Pint** code style enforcement.
- **Deptrac** domain boundary enforcement for modular monolith discipline.
- **OpenAPI generation and linting** via `dedoc/scramble`, plus contract snapshot checks.
- **CI quality gates** for tests, static analysis, style checks, proxy trust validation, and security baseline checks.

## Ecosystem

This backend is designed to pair perfectly with the following frontend:
- **[Obsidian Admin Vue](https://github.com/obsidianlabs-io/obsidian-admin-vue)**: A clean, elegant administrative interface based on Vue3, Vite, NaiveUI, and TypeScript (Derived from Soybean Admin).

## Version

- **Laravel Version**: 12.x
- **PHP Version**: 8.2+

## Usage

**Environment Preparation**

Make sure your environment meets the following requirements:
- **PHP**: >= 8.2
- **Composer**: >= 2.x
- **Database**: MySQL 8+ / PostgreSQL 14+ / SQLite (local-only is fine)
- **Cache**: Redis 6+

**Clone Project**

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
```

**Run and Deploy (Two Options, pick one)**

You can launch the backend using the traditional PHP Artisan environment for active development, or utilize the fully provisioned Docker Compose stack for a production-ready setup.

### Option 1: Native PHP Development (Local)

If you have `php` and `composer` installed locally, you can use traditional Artisan commands.

> [!TIP]
> `.env.example` is Docker-friendly by default (`MySQL + Redis`). If those services are not available locally, update `.env` before running migrations.

```bash
# 1. Enter the project directory and copy the environment file
cd obsidian-admin-laravel
cp .env.example .env

# 2. Install Composer dependencies
composer install

# 3. Generate the application key
php artisan key:generate

# 4. Run migrations and seeders (ensure your configured database is available)
php artisan migrate --seed

# 5. Start the development server
php artisan serve

# Or using RoadRunner/Octane for high performance
php artisan octane:start
```

**Run Test Suite**

```bash
composer run test
```

### Option 2: Docker Containerized Deployment (Production)

The project ships with a production Compose configuration using an image-based deployment model (no host source bind mount), including `PHP-FPM`, `Nginx`, `MySQL`, `Redis`, and `Horizon` queue listeners.

**1. Start all services (production image-based deployment)**

```bash
docker compose -f docker-compose.production.yml up -d --build
```

**2. Load data after the first start**

> [!IMPORTANT]
> You must re-run this command whenever you recreate the stack with `down -v` and lose your data volumes.

```bash
docker exec obsidian-admin-laravel-app-1 php artisan migrate --force --seed
```

**Ensure `.env` queue and cache drivers are correct**

> [!WARNING]
> This project uses Laravel Horizon to process async queue jobs (e.g. audit logs). Horizon **only supports the Redis driver**. If set to `database`, queued jobs will NOT be processed automatically.
>
> Make sure your `.env` contains:
> ```env
> QUEUE_CONNECTION=redis
> CACHE_STORE=redis
> ```
> After modifying `.env`, you must recreate containers with `--force-recreate` (a simple `restart` will NOT reload environment variables):
> ```bash
> docker compose -f docker-compose.production.yml up -d --force-recreate
> ```

**Verify service health**

```bash
curl http://localhost:8080/api/health
```

A response of `"status": "ok"` confirms all services are running correctly.

**Stop services**

```bash
# Stop containers only (data volumes preserved)
docker compose -f docker-compose.production.yml down

# Stop and remove all data volumes (destructive)
docker compose -f docker-compose.production.yml down -v
```

| Service | Description | Exposed Port |
|---|---|---|
| `app` | PHP-FPM application container | 9000 (internal) |
| `nginx` | Web server / reverse proxy | **8080** |
| `mysql` | MySQL 8.0 database | 3306 |
| `redis` | Redis 7 cache & queue | 6379 |
| `horizon` | Laravel Horizon queue dashboard | - |
| `scheduler` | Laravel task scheduler | - |
| `pulse-worker` | Laravel Pulse monitoring worker | - |
| `reverb` | Laravel Reverb WebSocket server | 6001 |

## Common Commands

```bash
# Run tests (Pest)
composer run test

# Code style check
vendor/bin/pint --test

# Static analysis
vendor/bin/phpstan analyse --memory-limit=1G

# Security baseline check (strict)
php artisan security:baseline --strict

# Trusted proxy config validation (strict)
php artisan http:proxy-trust-check --strict

# OpenAPI / contract checks
php artisan openapi:lint
php artisan api:contract-snapshot --check
```

## Common Endpoints / Consoles

- Health check: `GET /api/health`
- OpenAPI docs: `/docs/api` (depends on `API_DOCS_ENABLED`)
- Horizon: `/horizon` (depends on deployment and auth config)
- Pulse: `/ops/pulse` (depends on deployment and auth config)

## Acknowledgements

Obsidian Admin Laravel's architecture draws profound inspiration from exceptional open-source projects and architectural minds. Special thanks to **[DTO](https://github.com/spatie/data-transfer-object)** (Spatie) and the evangelists of Modular Monolithic applications for heavily influencing this structural paradigm shift away from bloated controllers.

## License

This project is released under the [MIT License](./LICENSE).

*Copyright © 2026 Obsidian Labs.*
