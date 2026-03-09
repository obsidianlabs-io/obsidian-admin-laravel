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

## Request Boundary Rule

- Complex write/list endpoints should follow `FormRequest -> toDTO() -> Action/Service -> Resource/Response`.
- Controllers should not read `validated()` arrays directly once a typed DTO exists for that endpoint.
- Keep request-only concerns in `FormRequest`:
  - input normalization
  - legacy field compatibility
  - shape validation
- Keep domain-only concerns in actions/services:
  - role level rules
  - tenant scope checks
  - persistence and transactions

## Service Command Rule

- Services should prefer explicit command DTOs over `array $payload` inputs for non-trivial writes.
- Input DTOs can stay request-oriented, but they should convert into service-facing command DTOs before the service call.
- Keep the controller orchestration thin:
  - resolve request DTO
  - enrich context ids
- convert to command DTO
- call service/action
- Avoid inline mutation payload arrays in controllers, including deactivate/soft-delete update flows.

## Query Action Rule

- Complex list endpoints should move query construction and filtering into dedicated query actions.
- Query actions should accept typed list input DTOs plus explicit scope context values.
- Keep controller list methods focused on:
  - resolving auth/scope context
  - calling the query action
- applying pagination helper
- resolving HTTP resources
- Avoid duplicating `with(...)`, `withCount(...)`, visibility scope, and filter clauses inside controllers.

## Pagination Payload Rule

- Shared list controllers should use the common pagination payload builders from `ApiController`.
- Keep pagination response shape centralized for both cursor and offset modes.
- Controllers should only:
  - resolve resources into record arrays
  - pass records and metadata into the shared pagination payload builder
- Avoid hand-building repeated pagination response arrays with:
  - `paginationMode`
  - `hasMore`
  - `nextCursor`
  - `current`
  - `total`

## Snapshot / Response Data Rule

- Repeated mutation audit snapshots and mutation response payloads should move into typed data objects once the shape is reused.
- Prefer domain-local data classes such as `TenantSnapshot`, `RoleResponseData`, or `PermissionSnapshot` over controller-private helper arrays.
- Prefer typed response data for shared system payloads as well, for example theme config responses or feature flag override responses.
- Keep controllers focused on:
  - choosing the right snapshot/response data object
  - converting it to array only at the HTTP or audit boundary
- Avoid reintroducing private controller helper methods such as `tenantSnapshot()` or `roleResponse()` for reused payload shapes.

## Service Result Rule

- Public service methods that return reused business payloads should prefer typed result objects over raw arrays.
- Good candidates are:
  - policy evaluation results
  - history page payloads
  - prune summaries
  - scope/config resolution results
- stateful shared boundaries such as idempotency/session projection results
- Keep raw arrays only for:
  - framework integration boundaries
  - direct config ingestion
  - short-lived private helpers that do not escape the service

## Scaffolding

- `php artisan make:domain-resource {Domain} {Resource}` scaffolds a bounded resource baseline aligned with the current Laravel patterns.
- Generated files include:
  - list/create/update DTOs
  - list/store/update requests
  - list query action
  - domain service
  - list resource
  - controller
  - skipped feature test placeholder
- Use `--scope=tenant|platform` to choose the generated scope semantics.
- Use `--base-path=...` for temporary generation in tests or external worktrees.
- The generator does not wire routes, policies, migrations, or OpenAPI paths; those remain explicit follow-up steps.

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
- Keep deletion lifecycle rules centralized in `docs/deletion-governance.md`.
