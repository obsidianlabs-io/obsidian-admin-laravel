# Multi-Tenancy

## Tenant model

This backend uses an explicit tenant-context model instead of pretending every request is globally scoped.

The main building blocks are:

- `tenant`
- `organization`
- `team`
- tenant-aware users, roles, and audit logs

Relevant code lives under:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Domains/Tenant`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Domains/Shared/Auth`

## Scope model

The project supports two high-level scopes:

- platform scope
- selected tenant scope

That distinction matters for super admins.

Examples:

- platform scope can access global capabilities such as platform-level policy or theme configuration
- selected tenant scope narrows user, audit, organization, and team behavior to one tenant

## Tenant context resolution

Tenant context is resolved centrally, not page by page.

Key files:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Domains/Tenant/Services/TenantContextService.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Http/Middleware/ResolveTenantContext.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Domains/Shared/Auth/TenantContext.php`

The request header used for explicit tenant selection is:

- `X-Tenant-Id`

The backend is responsible for deciding whether that header is valid for the current actor. The frontend must not treat the header as an authorization mechanism by itself.

## Safety model

This repository deliberately applies tenant safety in multiple layers:

1. request / middleware context resolution
2. controller and service scope checks
3. database-level integrity constraints
4. feature tests that try to cross tenant boundaries directly

That layering is intentional. Multi-tenancy is too important to protect with only one mechanism.

## What is already covered

Current project guarantees include:

- super-admin platform vs selected-tenant distinction
- tenant-safe user / role / permission management
- tenant-safe organization and team relationships
- tenant-aware audit log visibility
- cross-tenant leak regression tests

Representative tests:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/TenantBoundaryApiTest.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/AuditLogApiTest.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/tests/Feature/RbacDoctorCommandTest.php`

## Role and tenant interaction

This backend does not treat RBAC and tenancy as separate concerns.

Important rules already enforced in the repository:

- role visibility depends on actor level and scope
- tenant users cannot be managed outside the active tenant context
- selected-tenant super-admin behavior is intentionally different from platform-scope super-admin behavior
- organization/team membership must remain tenant-consistent

## Frontend pairing rule

The intended frontend pairing is:

- `/Users/zero/Documents/Project/WK/obsidian-admin-vue/docs/multi-tenancy.md`

The frontend reacts to tenant changes, but the backend remains the source of truth for:

- effective tenant context
- tenant-scoped menu payloads
- tenant-safe resource visibility
- tenant-scoped validation failures

## Design rule

Do not collapse this into a generic `tenant_id` convention and assume the problem is solved.

A serious multi-tenant backend needs:

- explicit scope resolution
- explicit platform-vs-tenant behavior
- explicit tests for cross-tenant leaks
- explicit integration with auth, routing, and audit behavior
