# Backend / Frontend Compatibility Matrix

This repository is versioned independently from `obsidian-admin-vue`.

## Supported pairs

| Backend | Frontend | Status | Notes |
| --- | --- | --- | --- |
| `main` | `main` | Active development | CI and local contract tooling assume both repositories move together. |
| `v1.2.0` | `v1.1.1` | Stable | Current documented release pair. |

## Source of truth

- Backend API contract: `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/openapi.yaml`
- Frontend generated SDK target: `/Users/zero/Documents/Project/WK/obsidian-admin-vue/src/service/api/generated`
- Frontend API contract snapshot: `/Users/zero/Documents/Project/WK/obsidian-admin-vue/docs/api-client-contract.snapshot`

## Upgrade rule

When backend API contract changes:

1. update `docs/openapi.yaml`
2. regenerate frontend API types and SDK in `obsidian-admin-vue`
3. run:
   - `php artisan openapi:lint`
   - `php artisan test tests/Feature/OpenApiContractTest.php tests/Feature/OpenApiLintCommandTest.php tests/Feature/ApiContractSnapshotCommandTest.php`
   - `pnpm -C ../obsidian-admin-vue typecheck:api`
   - `pnpm -C ../obsidian-admin-vue check:ci`

If the backend contract introduces a breaking change for the published frontend release pair, publish a new coordinated release and update this matrix.
