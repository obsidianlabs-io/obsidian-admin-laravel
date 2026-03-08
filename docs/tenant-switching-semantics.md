# Tenant Switching Semantics

Tenant switching in this repository is a security boundary, not a cosmetic UI state.

That distinction matters because the frontend can request a tenant scope change, but the backend is the only authority that decides whether the new scope is valid.

## 1. Two top-level modes

The backend supports two effective access modes:

- platform scope
- selected tenant scope

Platform scope is where global operators can access platform-level capabilities such as:

- tenant management
- platform theme configuration
- platform-wide audit policy

Selected tenant scope narrows the request context to one tenant and changes what the actor can see and mutate.

## 2. The request signal

The frontend sends the requested tenant via:

- `X-Tenant-Id`

That header is only a request hint.

It is not an authorization mechanism.

The backend validates:

- whether the actor is allowed to select that tenant
- whether the requested tenant exists
- whether the resulting scope is still compatible with the actor's role level and capabilities

## 3. Why this is treated as semantics, not state

If tenant switching is implemented as "frontend state only", the system becomes easy to misuse:

- the UI may appear to switch tenant while API scope does not
- direct requests may attempt to override scope
- privileged users may accidentally stay in platform scope while assuming they are tenant-scoped

This repository therefore treats tenant switching as request semantics resolved centrally on the backend.

## 4. Super admin behavior

Super admin behavior is intentionally different depending on scope:

- in platform scope, a super admin can access platform-level surfaces
- in selected tenant scope, the same actor is intentionally narrowed to tenant-level visibility and operations

This is not a limitation. It is a safety rule.

It prevents platform-level power from leaking into tenant-level workflows where the intent was to operate inside one tenant.

## 5. Non-super admin behavior

Tenant-bound operators do not "switch" like platform operators do.

Their effective tenant scope is already constrained by:

- their assigned tenant
- their organization and team relationships
- their role permissions

For those actors, the backend rejects attempts to escape or override tenant scope through headers or direct URL access.

## 6. What the frontend should assume

The paired frontend should assume:

- tenant switch affects menus, lists, and mutation permissions
- backend responses are the source of truth for current tenant context
- empty data after a scope change may be a real scope result, not necessarily a client bug

The frontend should not:

- trust its own switcher state more than the backend response
- treat a stored tenant id as permanent authorization
- assume platform-scope menus are still valid after tenant selection

## 7. Expected backend guarantees

When tenant switching is implemented correctly, the backend guarantees:

- invalid selected tenant values are rejected
- cross-tenant record access is blocked
- audit log visibility follows effective scope
- menu and user info payloads reflect current scope
- direct request overrides do not bypass scope rules

## 8. Where this is enforced

Representative backend areas:

- `app/Http/Middleware/ResolveTenantContext.php`
- `app/Domains/Tenant/Services/TenantContextService.php`
- `app/Domains/Shared/Auth/TenantContext.php`

Representative regression coverage:

- `tests/Feature/TenantBoundaryApiTest.php`
- `tests/Feature/AuditLogApiTest.php`
- `tests/Feature/RegressionApiSafetyFixesTest.php`

## 9. Recommended mental model

Treat tenant switching like this:

- the frontend requests a scope
- the backend resolves an effective scope
- every downstream query and policy uses that resolved scope

That model is much safer than pretending a tenant switcher is just a client-side filter.
