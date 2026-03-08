# RBAC and Role Levels

This backend uses role-based access control as the primary authorization model, but it does not stop at simple permission checks.

The access model combines:

- permission code checks
- tenant scope checks
- role level restrictions
- explicit controller and service safety rules

## 1. Permission-first authorization

Permission codes remain the first gate for protected actions.

Examples:

- `user.view`
- `user.manage`
- `role.view`
- `role.manage`
- `permission.view`
- `permission.manage`

These checks are enforced through:

- `api.permission:*` middleware
- explicit controller checks where defense-in-depth still matters
- policy and service-level safety for sensitive mutations

That layered approach is deliberate. Permission middleware alone is not enough for a serious admin backend.

## 2. Why role levels exist

Permissions answer:

- can this actor use this capability at all?

Role levels answer:

- can this actor manage that specific target?

This matters because two users may both have `user.manage`, but they should not necessarily be able to edit or delete each other.

Representative constraints already enforced in the repository:

- same-level users cannot manage each other
- only lower-level roles can be updated or deleted
- assignable roles are filtered by actor scope and actor level
- tenant admins cannot use role management to escape tenant boundaries

## 3. Scope still matters

Role levels do not replace tenancy.

This backend always combines:

1. permission check
2. scope check
3. level check

That means an actor may have permission to manage users in general, but still fail because:

- the target belongs to another tenant
- the role is global while the actor is tenant-scoped
- the target role level is equal to or above the actor's level

## 4. Practical rules already implemented

The repository already enforces these high-value rules:

- role list visibility can be broader than mutability
- same-level roles can be visible but not editable
- global roles and tenant roles follow different scope rules
- user management respects both `manageable` flags and actor role level
- assign-role flows validate scope and level before persisting changes

This is why frontend behavior can safely do things like:

- show a row
- allow view
- hide or disable edit/delete

without treating the frontend as the security boundary.

## 5. Backend source of truth

Relevant areas:

- `app/Domains/Access`
- `app/Policies`
- `tests/Feature/RegressionApiSafetyFixesTest.php`
- `tests/Feature/TenantBoundaryApiTest.php`
- `tests/Feature/RbacDoctorCommandTest.php`

The frontend may reflect these rules visually, but the backend remains authoritative for:

- whether a role is assignable
- whether a row is manageable
- whether an update/delete should be rejected

## 6. Design intent

This repository intentionally avoids the weak pattern of:

- "permission granted, therefore everything is manageable"

Instead, it treats access control as a combination of:

- capability
- scope
- hierarchy

That model is closer to how real internal platforms and SaaS control planes behave.
