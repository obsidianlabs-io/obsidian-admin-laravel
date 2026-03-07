# Contributing

Thanks for contributing to Obsidian Admin Laravel.

This repository is intended to be a production-grade Laravel 12 backend baseline. Contributions should improve correctness, maintainability, security, or operational clarity. Please avoid speculative abstractions and framework churn.

## Before You Start

- Use PHP `8.2+`
- Use Composer `2.x`
- Prefer Docker if you need the full local stack (`MySQL + Redis + Horizon + Reverb`)
- Read `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/architecture.md`
- Read `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/release-sop.md` if your change affects release gates

## Local Setup

### Option A: Docker

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
docker compose -f docker-compose.dev.yml run --rm composer
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

### Option B: Native PHP

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Optional Octane Runtime

The repository now ships with official Laravel Octane integration. To initialize the local RoadRunner runtime:

```bash
composer run octane:install
composer run octane:start
```

Read `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/octane.md` before changing Octane or RoadRunner behavior.

## Development Rules

- Keep controllers thin: `FormRequest -> DTO -> Action/Service -> Resource/Response`
- Keep domain boundaries intact under `app/Domains/*`
- Do not introduce raw request payload arrays back into high-value controller or service boundaries
- Prefer typed DTOs and typed result/data objects over unstructured arrays
- Do not add Laravel-specific global state patterns that break long-lived worker safety
- Do not weaken tenant safety, role-level governance, or audit coverage to make tests pass

## Validation Checklist

Run the relevant gates before opening a pull request.

### Minimum backend gate

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

### Recommended full backend gate

```bash
composer run quality:check
```

### Database matrix

If your change can affect query behavior, migrations, or database-specific SQL, run the database variants too:

```bash
composer run test:mysql
composer run test:pgsql
```

## API and Contract Changes

If you change request or response shapes:

```bash
php artisan api:contract-snapshot --write
php artisan openapi:lint
```

Keep the OpenAPI output and contract snapshot aligned with the actual controller surface.

## Pull Request Expectations

A good pull request should:

- have a narrow scope
- explain the problem and the chosen fix
- mention any schema, contract, queue, cache, or tenant-scope impact
- include tests when behavior changes
- avoid mixing refactors with unrelated formatting or cleanup

## What We Usually Reject

We are unlikely to accept pull requests that:

- replace coherent architecture with trend-driven rewrites
- introduce cross-domain shortcuts that bypass existing boundaries
- add framework coupling with little payoff
- weaken release gates, static analysis, or architecture tests
- add broad abstractions without a clear maintenance win
