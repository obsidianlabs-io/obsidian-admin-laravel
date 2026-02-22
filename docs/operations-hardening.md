# Operations Hardening Checklist

This baseline is designed for large, multi-tenant deployments.

## 1) Runtime Safety

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Set a strong `APP_KEY`.
- Enforce HTTPS and secure cookies (`SESSION_SECURE_COOKIE=true`).

## 2) Queue and Audit Reliability

- Use async queue for audit events (`AUDIT_QUEUE_ENABLED=true`).
- Use Redis queue backend in production (`QUEUE_CONNECTION=redis`).
- Run Horizon for queue orchestration:
  - `php artisan horizon`
- Keep Horizon metrics snapshots via scheduler:
  - `php artisan horizon:snapshot`
- Monitor `failed_jobs` and alert on growth.

## 3) Data and Cache

- Use Redis (or equivalent) for `CACHE_STORE` in production.
- Avoid `array` cache store in production.
- Run periodic cleanup for idempotency and audit retention:
  - `php artisan audit:prune`

## 4) Observability

- Keep `LOG_HTTP_REQUESTS=true` for request tracing, or disable only after external APM is in place.
- Propagate `X-Request-Id` and `traceparent` from edge gateway to backend.
- Correlate API logs and audit logs using `request_id` and `trace_id`.
- Run Pulse stream worker for high traffic environments:
  - `php artisan pulse:work`

## 5) Security Controls

- Enable stronger auth policy as needed:
  - `AUTH_SUPER_ADMIN_REQUIRE_2FA=true`
  - `AUTH_REQUIRE_EMAIL_VERIFICATION=true`
- Keep optimistic lock enabled for write-heavy consoles:
  - `OPTIMISTIC_LOCK_REQUIRE_TOKEN=true`
  - send `version` (preferred) or `updatedAt` on update requests
- Review and prune over-privileged roles regularly.

## 6) Health Monitoring

- Use `/api/v1/*` as canonical API version path.
- Keep `/api/*` compatibility only while frontend migration is in progress:
  - `API_LEGACY_UNVERSIONED_ENABLED=true|false`
- Check `/api/health/live` for process liveness.
- Check `/api/health/ready` for rollout readiness.
- Use `/api/health` and `/api/v1/health` for full diagnostics.
- Alert when status is `warn` or `fail`.
- Health response includes:
  - connection checks
  - production guardrail checks
  - deployment context (db/cache/queue/log settings)

## 7) Release Contract Gate

- Keep API route snapshot in version control: `docs/api-contract.snapshot`.
- Validate before merging:
  - `php artisan openapi:lint`
  - `php artisan security:baseline`
  - `php artisan api:contract-snapshot`
  - `php artisan test tests/Feature/OpenApiContractTest.php --filter=test_openapi_documented_operations_are_registered_for_root_and_v1_api_routes`
- If API contract change is intentional, update snapshot:
  - `php artisan api:contract-snapshot --write`

## 8) Project Bootstrap Profile

- For new environments, apply project-level defaults in one command:
  - `php artisan project:profile:apply base --write-env`
- Profile catalog and customization:
  - `docs/project-profiles.md`

## 9) Safe Feature Rollout

- Feature flags are managed by Pennant and can be scoped by tenant/role context.
- Check current value:
  - `php artisan feature:rollout menu.permission check --global`
- Disable globally:
  - `php artisan feature:rollout menu.permission off --global`
- Disable for one tenant/role scope:
  - `php artisan feature:rollout menu.role off --tenant=1 --roles=R_ADMIN`
