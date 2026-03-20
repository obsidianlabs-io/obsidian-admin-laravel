# Backend / Frontend Compatibility Matrix

This repository is versioned independently from `obsidian-admin-vue`.

## Supported pairs

| Backend | Frontend | Status | Notes |
| --- | --- | --- | --- |
| `main` | `main` | Active development | CI and local contract tooling assume both repositories move together. |
| `v1.3.0` | `v1.2.0` | Stable | Current documented release pair. Laravel 13 backend minor release without a required coordinated frontend tag. |
| `v1.2.1` | `v1.2.0` | Stable | Previous stable release pair. |
| `v1.2.0` | `v1.1.1` | Stable | Previous stable release pair. |

## Current coordinated backend lane

- Backend `main` now tracks the Laravel 13 baseline.
- The current stable published pair is backend `v1.3.0` with frontend `v1.2.0`.
- Frontend `v1.2.0` remains the compatible published frontend release because no backend contract break was introduced in the Laravel 13 backend minor.

## Source of truth

- Backend API contract: `docs/openapi.yaml`
- Frontend generated SDK target: `obsidian-admin-vue/src/service/api/generated`
- Frontend API contract snapshot: `obsidian-admin-vue/docs/api-client-contract.snapshot`

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
