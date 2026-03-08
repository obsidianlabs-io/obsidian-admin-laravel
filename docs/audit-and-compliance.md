# Audit and Compliance

This backend treats auditability as a product feature, not an afterthought.

That matters for:

- enterprise admin systems
- multi-tenant operations
- security-sensitive internal platforms
- change-traceability requirements

## 1. Audit model

The repository distinguishes between:

- audit event capture
- audit policy control
- audit visibility rules
- audit retention and pruning

Those concerns live primarily under:

- `app/Domains/System`
- `app/Domains/Tenant`
- `app/Domains/Auth`

## 2. What gets audited

Representative audited areas already covered include:

- login and session activity
- user and access mutations
- tenant-scoped administration
- feature flag changes
- audit policy changes
- profile and preference updates where configured

The system also keeps:

- `requestId`
- actor identity
- tenant context
- old/new values where relevant

That makes audit events useful for both debugging and compliance review.

## 3. Audit policy control

The repository does not treat every event as permanently fixed.

Audit policy supports:

- enabled / disabled state
- sampling rate
- retention days
- lock and mandatory behavior
- change history

That means teams can distinguish between:

- events that must always be recorded
- events that are configurable
- events that are configurable but still centrally governed

## 4. Tenant-aware audit visibility

Audit is not only about writing logs. It is also about making sure the right actors can see the right logs.

Current behavior already includes:

- tenant-scoped audit log visibility
- platform-scope vs selected-tenant super-admin behavior
- audit list filtering without cross-tenant leakage

Representative regression coverage:

- `tests/Feature/AuditLogApiTest.php`
- `tests/Feature/TenantBoundaryApiTest.php`

## 5. Queue and runtime behavior

Audit writes are designed to work with a production queue-backed runtime.

That matters because:

- synchronous audit writes increase request latency
- queue-backed audit writes are more realistic for Horizon / Redis deployments
- observability and compliance still need to survive runtime scale-up

The repository already includes queue-aware testing and runtime guidance for:

- Redis
- Horizon
- production compose
- Octane / RoadRunner paths

## 6. Retention and pruning

Compliance is not only about capturing logs. It also requires predictable retention behavior.

The backend includes:

- retention days per policy
- prune commands
- prune tests

This gives you a practical balance between:

- traceability
- storage control
- policy-driven lifecycle management

## 7. Contract and review surface

This repository exposes audit capabilities through:

- audit log APIs
- audit policy APIs
- OpenAPI documentation
- frontend pairing through generated SDK contracts

The intended operator review path is:

1. read the audit policy configuration
2. understand which actions are mandatory or configurable
3. review runtime and retention behavior
4. confirm audit visibility stays tenant-safe

## 8. Design intent

The project deliberately avoids a shallow "write some logs to a table" audit model.

Instead, it treats compliance-oriented auditing as a combination of:

- explicit policy
- explicit scope
- explicit history
- explicit retention
- explicit runtime behavior

That is the level required for a serious Laravel admin backend baseline.
