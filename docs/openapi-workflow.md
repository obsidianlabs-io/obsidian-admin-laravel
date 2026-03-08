# OpenAPI Workflow

## Goal

The OpenAPI document is not decorative. In this project it is part of the runtime contract workflow between backend and frontend.

Canonical files:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/openapi.yaml`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/api-contract.snapshot`
- `/Users/zero/Documents/Project/WK/obsidian-admin-vue/src/service/api/generated`

## Source of truth chain

The intended chain is:

1. Laravel routes and controllers
2. documented OpenAPI operations and schemas
3. backend contract snapshot checks
4. frontend generated SDK and typings
5. frontend app-facing API facades

If any link in that chain drifts, the CI gates are supposed to fail.

## Backend commands

For backend contract work, use:

```bash
php artisan openapi:lint
php artisan api:contract-snapshot
php artisan api:contract-snapshot --write
```

Composer shortcuts:

```bash
composer run openapi:lint
composer run contract:check
composer run contract:write
```

## Frontend regeneration path

After backend OpenAPI changes, regenerate frontend artifacts in:

- `/Users/zero/Documents/Project/WK/obsidian-admin-vue`

Commands:

```bash
pnpm api:types
pnpm openapi:client:official
pnpm typecheck:api
```

For release-grade confidence:

```bash
pnpm check:ci
```

## What to update when you add an endpoint

At minimum, check these items:

1. route exists and is covered by tests
2. OpenAPI operation exists
3. request schema is specific enough to generate useful frontend types
4. response schema is specific enough to avoid `unknown` everywhere
5. examples are added for high-value or non-obvious endpoints
6. frontend generated SDK stays clean after regeneration

## What not to do

Do not treat `ApiResponse` alone as sufficient documentation for important endpoints.

High-value endpoints should describe field-level payloads, especially for:

- auth session flows
- user / role / permission CRUD
- tenant / organization / team management
- audit logs and audit policies
- feature flags and CRUD schema payloads

## CI gates

Representative backend checks:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/OpenApiContractTest.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/OpenApiLintCommandTest.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/ApiContractSnapshotCommandTest.php`

Representative frontend checks:

- `/Users/zero/Documents/Project/WK/obsidian-admin-vue/scripts/api-client-contract.mjs`
- `/Users/zero/Documents/Project/WK/obsidian-admin-vue/scripts/api-architecture-check.mjs`
- `/Users/zero/Documents/Project/WK/obsidian-admin-vue/docs/compatibility-matrix.md`

## Rule of thumb

If a backend change can break generated frontend behavior, it is not "just a backend refactor".

Update the OpenAPI contract, regenerate the frontend SDK, and let the gates prove the change is safe.
