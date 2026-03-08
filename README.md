<div align="center">
	<img src="./public/favicon.svg" width="160" />
	<h1>Obsidian Admin Laravel</h1>
  <span>English | <a href="./README.zh_CN.md">中文</a></span>
</div>

---

[![license](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)

> [!NOTE]
> `Obsidian Admin Laravel` is a robust, production-ready enterprise backend boilerplate tailored specifically to act as the API foundation for standard Vue3/React admin dashboards (like Obsidian Admin Vue).
>
> The repository now ships with official Laravel Octane integration, a tracked `.rr.yaml` baseline, and RoadRunner-oriented defaults. The machine-specific RoadRunner binary is still generated locally, so review [`docs/octane.md`](./docs/octane.md) before using Octane in development or production.
>
> Stable release tags also publish an immutable multi-arch GHCR image at `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:<tag>` for `linux/amd64` and `linux/arm64`. Stable non-prerelease tags also update `latest`.

## Release Image

Pull the release image directly from GHCR:

```bash
docker pull ghcr.io/obsidianlabs-io/obsidian-admin-laravel:v1.2.0
```

Quick runtime example:

```bash
docker run --rm -p 8080:8000 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:Q2qE5A3yM4tQvL3X0yr7M5m4r2m40fX9zCw1Q2m3N4o= \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e AUDIT_QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  -e LOG_STACK=stderr \
  ghcr.io/obsidianlabs-io/obsidian-admin-laravel:v1.2.0
```

For queue, database, Redis, and nginx-backed deployments, use the runtime guidance in [`docs/production-runtime.md`](./docs/production-runtime.md).

The GHCR release image is verified in CI for cold boot and Laravel bootstrap. For route-level HTTP health probes, use the compose-backed runtime path documented in [`docs/production-runtime.md`](./docs/production-runtime.md).

The repository now runs two complementary image scanning paths:

- `Backend Supply Chain` builds the current runtime image on pull requests, `main`, and nightly schedule, then uploads `backend-runtime-image-scan`
- stable release tags scan the just-published GHCR image and upload `backend-release-image-scan`

### Tag Strategy

Stable backend releases publish the following GHCR tags:

- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:1.2.0`
- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:1.2`
- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:1`
- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:latest` for stable non-prerelease tags only

Use the fully versioned tag in production. Reserve `latest` for evaluation or internal smoke checks.

### Compose Consumption Example

If you want to consume the published image directly instead of building locally, use a compose override like this:

```yaml
services:
  app:
    image: ghcr.io/obsidianlabs-io/obsidian-admin-laravel:1.2.0
    pull_policy: always
```

Then start the stack normally:

```bash
docker compose -f docker-compose.production.yml -f docker-compose.image.yml up -d
```

## Quick Start

Octane / RoadRunner runtime is available through `docker-compose.octane.yml` when you want a production-like long-lived worker stack.

If you just want to get the API running quickly, choose one of the following:

## Key Docs

- Docs URL: [https://obsidianlabs-io.github.io/obsidian-admin-laravel/](https://obsidianlabs-io.github.io/obsidian-admin-laravel/)
- Compatibility matrix: [`docs/compatibility-matrix.md`](./docs/compatibility-matrix.md)
- Full-stack evaluation: [`docs/full-stack-evaluation.md`](./docs/full-stack-evaluation.md)
- Octane / RoadRunner runtime: [`docs/octane.md`](./docs/octane.md)
- Production runtime stack: [`docs/production-runtime.md`](./docs/production-runtime.md)
- Release sign-off checklist: [`docs/release-final-checklist.md`](./docs/release-final-checklist.md)

> The docs site is published through GitHub Pages. If Pages has not been enabled for the repository yet, this URL will return `404` until the first deployment finishes.

### Option A: Docker Development (Recommended, least setup friction)

Best for first-time setup, team-standardized environments, or when you want the full `MySQL + Redis + Horizon + Reverb` stack for local development.

> [!TIP]
> If you are on **native Windows**, prefer Docker Desktop (or WSL2). `Laravel Horizon` depends on `pcntl/posix`, which is typically unavailable in native Windows PHP environments.

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

# Start the full development stack
# The one-shot composer service now installs vendor automatically before app starts.
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

Health check:

```bash
curl http://localhost:8080/api/health
```

> [!NOTE]
> To avoid intermittent `storage/logs/laravel.log` permission errors on Windows, the dev stack now applies two defaults:
> 1) container logs go to `stderr` (`docker logs`), and
> 2) `storage` and `bootstrap/cache` use dedicated Docker volumes (not direct Windows bind-mounted folders).
>
> The dev stack also uses a one-shot `composer` service with the same PHP extension baseline as the app image, so Docker builds no longer drift from RoadRunner/Octane dependencies like `ext-sockets`.
>
> If you previously ran an older stack and hit log permission errors, rebuild the dev stack (this resets local dev data):
> ```bash
> docker compose -f docker-compose.dev.yml down -v
> docker compose -f docker-compose.dev.yml up -d --build
> docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
> ```
>
> If `composer.json` or `composer.lock` changes and you only want to refresh dependencies without recreating the whole stack:
> ```bash
> docker compose -f docker-compose.dev.yml run --rm composer
> ```

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

[`Obsidian Admin Laravel`](https://github.com/obsidianlabs-io/obsidian-admin-laravel) is a highly structured, scalable, and secure backend template built on **Laravel 12**. Unlike standard monolithic applications with fat controllers, this project enforces strict **Clean Architecture** patterns, pushing business logic into specialized Services and using **Data Transfer Objects (DTOs)** for strict type safety. It features native true multi-tenancy, enterprise Role-Based Access Control (RBAC), built-in audit logging, and official **Laravel Octane** integration with RoadRunner-oriented defaults and worker-safe request lifecycle guards.

## The Vision

Obsidian was founded by **Boss · Beyond · Black** — three distinct forces united by one vision.

**Boss** embodied leadership and structure.
**Beyond** represented innovation and the courage to challenge limits.
**Black** stood for depth, precision, and strategic clarity.

Though our journeys unfold apart, the foundation we forged remains eternal.

Obsidian continues to evolve — rooted in resilience and order, marching steadfast toward enduring value.

## Features

### Architecture & Domain Design

- **Laravel 12 + PHP 8.4+** with support for `MySQL / PostgreSQL / SQLite` and `Redis` for cache/queues.
- **Modular Monolith structure** organized by `app/Domains/*` (e.g. `Auth / Access / Tenant / System / Shared`).
- **Layered design** following `Controller -> DTO -> Service -> Model` to reduce fat controllers.
- **DTO-driven write flows** for safer, more maintainable request-to-domain boundaries.
- **Dynamic CRUD Schema API** to power schema-driven frontend pages (forms/tables/search schemas).

### Multi-Tenancy & Access Control

- **Platform + Tenant dual scope** (`No Tenant` platform scope and tenant-selected scope).
- **Tenant context resolution** with centralized `X-Tenant-Id` handling and super admin tenant switching rules.
- **Tenant safety boundaries** enforced through backend scope checks, database constraints, and cross-tenant tests.
- **Tenant -> Organization -> Team model support** with user organization/team binding and strict backend consistency checks (team belongs to organization and tenant scope).
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

- **Official Laravel Octane integration** with RoadRunner-oriented defaults and request-state leak safeguards.
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
- **CycloneDX SBOM generation + attestation** in CI for a reproducible, attestable runtime dependency inventory.
- **CI quality gates** for tests, static analysis, style checks, proxy trust validation, and security baseline checks.

## Ecosystem

This backend is designed to pair perfectly with the following frontend:
- **[Obsidian Admin Vue](https://github.com/obsidianlabs-io/obsidian-admin-vue)**: A clean, elegant administrative interface based on Vue3, Vite, NaiveUI, and TypeScript (Derived from Soybean Admin).

## Usage

**Environment Preparation**

Make sure your environment meets the following requirements:
- **PHP**: >= 8.4
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

# Optional: initialize the local RoadRunner binary and run Octane
php artisan octane:install --server=roadrunner
php artisan octane:start --server=roadrunner
```

See [`docs/octane.md`](./docs/octane.md) for the exact support model, local binary requirements, and production notes.

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
| `nginx` | Web server / reverse proxy | **8080** (default, configurable via `APP_HTTP_PORT`) |
| `mysql` | MySQL 8.0 database | internal only |
| `redis` | Redis 7 cache & queue | internal only |
| `horizon` | Laravel Horizon queue dashboard | - |
| `scheduler` | Laravel task scheduler | - |
| `pulse-worker` | Laravel Pulse monitoring worker | - |
| `reverb` | Laravel Reverb WebSocket server | 6001 (default, configurable via `REVERB_PUBLIC_PORT`) |

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


## Octane Runtime

```bash
docker compose -f docker-compose.octane.yml up -d --build
```

This starts the application on RoadRunner with the tracked `.rr.yaml` baseline. The runtime compose file does not bind-mount the repository, so the container downloads and uses a Linux-compatible `rr` binary.
