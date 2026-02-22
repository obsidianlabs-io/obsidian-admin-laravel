<div align="center">
	<img src="./public/favicon.svg" width="160" />
	<h1>Obsidian Admin Laravel</h1>
  <span><a href="./README.md">中文</a> | English</span>
</div>

---

[![license](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)

> [!NOTE]
> `Obsidian Admin Laravel` is a robust, production-ready enterprise backend boilerplate tailored specifically to act as the API foundation for standard Vue3/React admin dashboards (like Obsidian Admin Vue). 

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

- **Modern Technology Stack**: Powered by PHP 8.2+, Laravel 12, PostgreSQL, and Redis.
- **High Performance**: Pre-configured to run on **Laravel Octane (RoadRunner)**, providing enterprise-grade speed and asynchronous capabilities.
- **Clean Architecture**: Adheres strictly to `Controller -> DTO -> Service -> Model` design. Say goodbye to messy "fat controllers".
- **Strict Data Transfer Objects (DTO)**: Built-in `DTO` pattern to enforce rigid typing between HTTP requests and business services.
- **True Multi-Tenancy**: Built-in support for global platforms and isolated tenant boundaries with seamless cross-tenant switching.
- **Enterprise RBAC**: Rock-solid Role-Based Access Control out of the box. Fully integrated with backend guards and frontend dynamic route resolution.
- **Centralized Exception Handling**: Beautiful, predictable JSON error responses handled globally. No unhandled HTML exception pages on API endpoints.
- **Comprehensive Audit Logging**: Scalable audit logging implementation tracking user actions across platform and tenant scopes.
- **Extensive Test Coverage**: Fully functional Pest/PHPUnit test suite ensuring architectural integrity and security.

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
- **Database**: PostgreSQL 14+
- **Cache**: Redis 6+

**Clone Project**

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
```

**Run and Deploy (Two Options)**

You can launch the backend using the traditional PHP Artisan environment for active development, or utilize the fully provisioned Docker Compose stack for a production-ready setup.

### Option 1: Native PHP Development (Local)

If you have `php` and `composer` installed locally, you can use traditional Artisan commands:

```bash
# 1. Copy the environment variables
cp .env.example .env

# 2. Install Composer dependencies
cd obsidian-admin-laravel
composer install

# 3. Generate the application key
php artisan key:generate

# 4. Run migrations and seeders (Ensure local MySQL and Redis are running)
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

The project ships with an extremely comprehensive Docker Compose stack including `PHP-FPM`, `Nginx`, `MySQL`, `Redis`, and `Horizon` queue listeners for instant high-availability deployments.

**1. Start all services**

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

## Acknowledgements

Obsidian Admin Laravel's architecture draws profound inspiration from exceptional open-source projects and architectural minds. Special thanks to **[DTO](https://github.com/spatie/data-transfer-object)** (Spatie) and the evangelists of Modular Monolithic applications for heavily influencing this structural paradigm shift away from bloated controllers.

## License

This project is released under the [MIT License](./LICENSE).

*Copyright © 2026 Obsidian Labs.*
