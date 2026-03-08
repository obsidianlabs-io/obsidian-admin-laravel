# Full-Stack Demo Environment

Use this page when you want a real public demo environment that goes beyond the static frontend preview.

The current repository already supports:

- backend runtime via Docker, Octane, and GHCR images
- frontend public preview via GitHub Pages
- cross-repository pairing smoke via Playwright against the real backend

This guide defines the next step: a stable hosted demo environment for evaluators.

## Goal

Provide a public, low-risk environment where a visitor can:

- sign in with seeded credentials
- switch tenant scope
- browse real user, role, tenant, audit, organization, and team pages
- interact with selected create and edit drawers
- observe feature flags, audit logs, and runtime behavior against a real Laravel backend

## Non-Goals

This demo environment should not be:

- a writable shared staging environment for arbitrary destructive testing
- a replacement for local development
- a promise that every repository feature is safe to modify publicly

## Recommended Topology

Use this runtime split.

- frontend: deployed static build
- backend: deployed container image from GHCR
- database: managed MySQL or PostgreSQL instance
- cache / queue / websocket: managed Redis
- edge: reverse proxy or platform ingress

Recommended backend runtime path:

- default: `nginx + php-fpm`
- optional advanced path: `Octane + RoadRunner`

Use the default runtime first. Keep Octane for a second phase once the demo is stable.

## Demo Data Model

Seed the environment with predictable records.

- one platform scope
- one main tenant
- one branch tenant
- one super account
- one main-tenant admin account
- one small set of users, roles, permissions, organizations, teams, feature flags, and audit logs

The data must be deterministic enough that smoke tests and docs examples remain valid.

## Access Policy

Use one of these two patterns.

### Option A. Read-mostly public demo

- public credentials are documented
- mutating endpoints are selectively disabled, rate limited, or auto-reset
- safest option for broad exposure

### Option B. Short-lived writable demo

- public credentials are documented
- write actions are allowed
- data is reset on a schedule
- good for evaluations, but requires stronger cleanup discipline

Recommended first release:

- use Option A
- allow safe mutations only in selected modules such as language, organization, or team demo records
- block destructive high-impact actions in public mode

## Reset Strategy

A real public demo must reset itself.

Recommended reset model:

- nightly `migrate:fresh --seed`
- optional hourly cleanup job for known writable demo records
- publish a short note in docs that the demo is reset automatically

If you need write-enabled flows during product evaluations, create a second private demo instead of expanding the public one.

## Security Baseline

Treat the public demo as an internet-exposed environment, not as a toy.

- never expose production secrets
- use dedicated demo credentials and demo OAuth apps only
- use strict rate limits on auth endpoints
- keep queue, database, and Redis private to the runtime network
- monitor image scan and supply-chain workflows before each promoted demo update
- keep `security:baseline` green before rollout

## Smoke Coverage

A real demo environment should be backed by at least these checks.

- backend health probe
- frontend preview smoke
- full-stack pairing smoke
- optional external uptime probe for the public demo URL

Use the existing full-stack smoke as the minimum acceptance test before every demo deployment.

## Deployment Options

Recommended pragmatic options:

1. backend on a small VM or container platform using the GHCR image
2. frontend static build on GitHub Pages, Vercel, or Netlify
3. managed MySQL/PostgreSQL + managed Redis

If you want the simplest first release, keep backend and demo data private to a single cloud project and expose only the frontend and API ingress.

## Suggested Rollout Plan

### Phase 1

- public frontend preview only
- backend docs site public
- no public live backend

### Phase 2

- private full-stack demo for evaluators
- seeded credentials
- nightly reset
- monitored health checks

### Phase 3

- public full-stack demo
- limited safe write paths
- scheduled reset and uptime monitoring

This sequence keeps risk low while still moving toward a true public product experience.
