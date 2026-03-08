# Evaluator Demo Validation

Use this page right after the first evaluator demo is deployed.

This checklist is narrower than the full release checklist. It focuses only on the minimum signals that prove the hosted backend + frontend demo is usable, trustworthy, and consistent with repository docs.

## 1. Backend health

Run these first.

```bash
curl --fail --silent https://demo-api.example.com/api/health/live
curl --fail --silent https://demo-api.example.com/api/health/ready
docker compose -f docker-compose.demo.yml exec app php artisan about --only=environment
```

Pass conditions:

- live returns `status: alive`
- ready returns success
- artisan bootstrap succeeds inside the running runtime image

## 2. Seeded auth flow

Confirm the evaluator demo can authenticate with the documented seeded credentials.

Pass conditions:

- login succeeds without browser console errors
- the expected post-login landing page loads
- the current tenant context is visible in the header

## 3. Tenant switching

This is the first multi-tenant sanity check.

Pass conditions:

- switching from platform scope to `Main Tenant` succeeds
- switching back to platform scope succeeds
- the page does not blank, reload incorrectly, or lose auth state

## 4. High-value list pages

At minimum, verify these real backend pages load with seeded data.

- user list
- role list
- tenant list
- audit log list
- feature flag list

Pass conditions:

- the page renders without a fallback error state
- at least one seeded row is visible
- refresh does not produce auth or contract errors

## 5. Safe writable drawers

Verify only the allowed demo-safe write paths.

Recommended first set:

- language create or edit
- organization create or edit
- team create or edit

Pass conditions:

- drawer opens
- required options load
- save succeeds
- resulting data appears in the list
- the change is acceptable under the demo reset policy

Do not use destructive or high-impact flows as the first demo acceptance check.

## 6. Realtime and feature toggles

If the evaluator demo exposes Reverb and feature flags, validate one simple interaction.

Pass conditions:

- the feature flag page loads
- toggling a demo-safe flag reflects the expected UI state
- websocket-related behavior does not throw browser errors

If realtime is intentionally disabled for the first demo cut, document that explicitly instead of silently failing.

## 7. Contract integrity

Before sharing the evaluator URL, confirm pairing is still valid.

### Frontend repository

```bash
pnpm typecheck:api
pnpm test:fullstack
```

### Backend repository

```bash
php artisan openapi:lint
```

Pass conditions:

- generated frontend contract is in sync
- full-stack pairing smoke is green
- backend OpenAPI still describes the current evaluator backend

## 8. Demo truth check

Confirm the public-facing docs are honest.

Pass conditions:

- the docs distinguish static preview from hosted evaluator demo
- seeded credentials are documented where intended
- reset policy is documented
- writable scope is documented
- unsupported or hidden flows are not advertised as publicly available

## 9. Stop conditions

Do not share the evaluator demo URL if any of these are true.

- live or ready health checks fail
- login fails with seeded credentials
- tenant switching is unstable
- user or audit pages fail to load
- demo-safe save flows fail
- `pnpm test:fullstack` is red
- docs imply broader public support than the deployed environment actually provides
