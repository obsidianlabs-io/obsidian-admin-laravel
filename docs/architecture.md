# Backend Architecture (Laravel 12 API-only)

## Modular Domain Layout

- `app/Domains/Access/*`: users, roles, permissions.
- `app/Domains/Auth/*`: login/session/security/profile.
- `app/Domains/Tenant/*`: tenant context and tenant CRUD.
- `app/Domains/System/*`: language, audit, theme, health, feature flags.
- `app/Domains/Shared/*`: cross-domain base controllers and shared services.

Each domain keeps its own `Http/Controllers`, `Services`, `Models`, and `Http/Resources` so features are extended inside one bounded folder.

## Cross-Cutting Layers

- `app/Policies/*`: authorization policy rules.
- `app/Http/Requests/Api/*`: input validation and normalization.
- `app/DTOs/*`: typed input DTOs for complex write operations.
- `app/Domains/*/Actions/*`: single-purpose use cases (for example user context/profile mapping).
- `app/Domains/*/Http/Controllers/Concerns/*`: reusable controller traits (for example login throttling).

## Route modules

- `routes/api/auth.php`: login/session/profile/security endpoints
- `routes/api/user.php`: user management
- `routes/api/access.php`: role/permission management
- `routes/api/tenant.php`: tenant management
- `routes/api/system.php`: language/audit/theme/health

All route modules are mounted from `routes/api.php` for both root `/api/*` and `/api/v1/*`.

Controller imports in route modules point to domain controllers under `App\Domains\...`.

## Auth and permission pipeline

- `api.auth` middleware: validates Sanctum token ability and user status.
- `api.permission:*` middleware: checks permission code(s) before controller logic.
- Controllers still use explicit checks for defense-in-depth and backward compatibility.

## Locale and preferences

- Locale/timezone/theme schema are sourced from `user_preferences`.
- `users` table stays focused on identity/auth fields.

## Scalability guardrails

- Keep idempotency only on mutation endpoints.
- Keep audit logging asynchronous where possible.
- Keep API contract snapshot and OpenAPI route tests as CI gates.
