# Deletion Lifecycle

Deletion in this repository is intentionally conservative.

The goal is not to make `DELETE` fast. The goal is to make it safe for:

- multi-tenant boundaries
- audit retention
- dependency-heavy admin data
- long-lived operations systems

## 1. The default posture

The backend treats deletion as a lifecycle, not a one-step action.

The expected path is:

1. active
2. inactive
3. soft-deleted
4. hard-deleted by scheduled purge

That sequence reduces the chance of:

- breaking foreign-key or assignment assumptions
- removing records that still matter to audit history
- losing operational recovery options too early

## 2. Why disable-first matters

For high-value admin resources, immediate hard delete is usually the wrong default.

Examples:

- users may still have active sessions or historical audit records
- roles may still be assigned
- permissions may still be attached to roles
- teams and organizations may still have active members
- tenants may still own scoped records

Disabling first gives operators a safe stop state before any irreversible action.

## 3. Recommended lifecycle by resource

| Resource | First step | Recovery expectation | Hard delete path |
| --- | --- | --- | --- |
| Tenant | Disable | Rare, carefully controlled | Scheduled only |
| Organization | Disable | Common during restructuring | Scheduled only |
| Team | Disable | Common during restructuring | Scheduled only |
| User | Disable + revoke sessions | Common for admin mistakes and HR churn | Scheduled only |
| Role | Disable | Common if assignments need cleanup | Scheduled only |
| Permission | Optional disable | Rare, but still safer than immediate purge | Scheduled only |

## 4. Guard rules

Deletion flow should always respect:

- tenant scope
- role level and permission checks
- protected seed records
- dependency counts
- audit logging
- session/token revocation for user-facing identities

For this reason, deletion is not just a repository concern. It is also an access-control and runtime-safety concern.

## 5. API behavior to prefer

The repository favors these semantics:

- `PATCH` or explicit state change when the goal is to disable
- `DELETE` only when the operation represents a soft-delete request
- queue/command-driven purge for permanent removal

That keeps synchronous API behavior predictable and preserves room for recovery and diagnostics.

## 6. What should be auditable

At minimum, the backend should emit audit records for:

- disable
- re-enable
- soft delete
- restore
- hard delete

That makes deletion reviewable during:

- incident analysis
- support investigations
- compliance checks

## 7. Relationship to retention

Deletion policy should not silently bypass retention policy.

If a record still contributes to:

- audit evidence
- tenant history
- security investigation context

then hard delete should remain a scheduled governance decision, not a routine UI click.

## 8. Where to look next

- [Deletion Governance](/deletion-governance)
- [Audit and Compliance](/audit-and-compliance)
- [RBAC and Role Levels](/rbac-and-role-levels)
- [Session and 2FA](/session-and-2fa)
- [Operations Hardening](/operations-hardening)
