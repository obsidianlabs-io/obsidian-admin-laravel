# Testing

## Testing strategy

This repository uses multiple verification layers because a Laravel admin backend can fail in multiple ways:

- style drift
- static type drift
- architecture boundary regression
- database-specific behavior drift
- runtime boot failure
- contract drift against the frontend

## 1. Local backend release gate

Run this first for real code changes:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

## 2. Stronger quality gate

For release-grade backend validation:

```bash
composer run quality:check
```

This includes:

- Pint
- PHPStan / Larastan
- architecture tests
- Deptrac
- OpenAPI lint
- API contract snapshot
- security baseline
- trusted proxy check

## 3. Database matrix

The repository tests against multiple databases.

Available commands:

```bash
composer run test
composer run test:mysql
composer run test:pgsql
```

CI also covers:

- SQLite
- MySQL
- PostgreSQL

That matters because multi-tenant and constraint-heavy code often behaves differently across engines.

## 4. Runtime smoke tests

This project treats runtime validation as part of testing, not as an afterthought.

Current CI smokes include:

- Octane + RoadRunner startup
- production compose runtime
- production image cold boot

Relevant workflow:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/.github/workflows/ci.yml`

## 5. Contract and security gates

For contract-sensitive or security-sensitive changes, also run:

```bash
php artisan openapi:lint
php artisan api:contract-snapshot
php artisan security:baseline
php artisan http:proxy-trust-check --strict
```

## 6. What to run by change type

### If you changed controller / service / DTO boundaries

```bash
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pest tests/Architecture --colors=always
php artisan test
```

### If you changed tenancy or RBAC behavior

```bash
php artisan test --filter=TenantBoundaryApiTest
php artisan test --filter=RegressionApiSafetyFixesTest
php artisan test
```

### If you changed OpenAPI or frontend-facing payloads

```bash
php artisan openapi:lint
php artisan api:contract-snapshot
pnpm -C ../obsidian-admin-vue typecheck:api
```

### If you changed Docker, Octane, or runtime boot logic

Run the normal backend gate, then do a smoke:

```bash
php artisan octane:start --server=roadrunner
curl --fail --silent http://127.0.0.1:8000/api/health/live
php artisan octane:stop --server=roadrunner
```

And, if needed:

```bash
APP_HTTP_PORT=18080 REVERB_PUBLIC_PORT=16001 docker compose -f docker-compose.production.yml up -d --build mysql redis app nginx
curl --fail --silent http://127.0.0.1:18080/api/health/live
docker compose -f docker-compose.production.yml down -v
```

## 7. Rule of thumb

Do not trust `php artisan test` alone for a release-critical infrastructure change.

For this repository, trustworthy release validation usually means:

```bash
composer run quality:check
php artisan test
```
