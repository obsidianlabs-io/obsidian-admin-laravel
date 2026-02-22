# Security Checklist (Backend API)

This checklist is aligned to a practical OWASP API baseline for this project.

## Automated Gates

1. `php artisan security:baseline`
2. `php artisan openapi:lint`
3. `php artisan test tests/Feature/PlatformHardeningApiTest.php`
4. `php artisan test tests/Feature/AuthApiTest.php`
5. `php artisan test tests/Feature/FeatureRolloutCommandTest.php`

## Baseline Controls

| Control | Purpose | Automated Gate |
| --- | --- | --- |
| `APP_KEY` set | Prevent weak crypto defaults | `security:baseline` |
| Login throttling (`AUTH_LOGIN_*`) | Reduce credential stuffing risk | `security:baseline`, `AuthApiTest` |
| Password minimum length | Enforce account credential quality | `security:baseline`, `AuthApiTest` |
| API auth coverage | Ensure non-public API routes require token auth | `security:baseline` |
| API permission coverage | Ensure management APIs are permission-guarded | `security:baseline` |
| Production hardening checks | Prevent debug/unsafe cookie/queue misconfig in prod | `security:baseline` |
| Tracing propagation | Keep request correlation via `traceparent` and `X-Trace-Id` | `PlatformHardeningApiTest` |
| Feature rollout persistence | Safe module rollout by tenant/role scope | `FeatureRolloutCommandTest` |
| OpenAPI quality | Keep API contract maintainable and reviewable | `openapi:lint` |

## OWASP API Mapping (Practical)

- API1 Broken Object Level Authorization:
  - Tenant boundary checks and permission middleware.
- API2 Broken Authentication:
  - Sanctum token flow + rate-limited login.
- API3 Broken Object Property Level Authorization:
  - FormRequest validation + service-layer controlled mutations.
- API4 Unrestricted Resource Consumption:
  - Login throttling + async audit queue + cursor pagination.
- API5 Broken Function Level Authorization:
  - Route-level `api.permission:*` enforcement.
- API6 Unrestricted Access to Sensitive Business Flows:
  - Security baseline route coverage checks.
- API8 Security Misconfiguration:
  - Production hardening checks in `security:baseline`.
- API10 Unsafe Consumption of APIs:
  - OpenAPI lint + contract snapshot gates.

## Recommended CI Order

1. `php artisan openapi:lint`
2. `php artisan security:baseline`
3. `php artisan api:contract-snapshot`
4. `php artisan test`
