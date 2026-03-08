---
layout: home

hero:
  name: Obsidian Admin Laravel
  text: Strictly-typed Laravel 12 admin backend baseline
  tagline: Production-ready Laravel backend with domain boundaries, multi-tenancy, OpenAPI contracts, official Octane integration, and release-grade CI gates.
  image:
    src: /favicon.svg
    alt: Obsidian Admin Laravel
  actions:
    - theme: brand
      text: Quick Start
      link: /getting-started
    - theme: alt
      text: Compatibility Matrix
      link: /compatibility-matrix
    - theme: alt
      text: GitHub Repository
      link: https://github.com/obsidianlabs-io/obsidian-admin-laravel

features:
  - title: Typed boundaries first
    details: Request DTOs, actions, services, and response data objects are enforced across high-value domains to keep controllers thin and behavior stable.
  - title: Multi-tenant safe by default
    details: Tenant context, scope enforcement, role levels, and cross-tenant guardrails are built into the backend baseline.
  - title: Modern Laravel runtime stack
    details: Octane, RoadRunner, Reverb, Horizon, Pulse, Sanctum, and Scramble are integrated without turning the project into a fragile demo stack.
  - title: Release-grade engineering gates
    details: Static analysis, architecture checks, supply-chain attestation, Docker smokes, Octane smokes, and multi-database CI all ship with the repository.
---

## Why this project exists

Most Laravel admin backends stop at CRUD scaffolding. This project is designed for long-lived internal platforms and SaaS control planes where tenant safety, API contracts, runtime discipline, and maintainability matter more than quick demos.

## Recommended pairing

Use this backend with [Obsidian Admin Vue](https://github.com/obsidianlabs-io/obsidian-admin-vue) if you want the full contract-driven admin stack.

## What to read next

- Start with [Getting Started](/getting-started)
- Review the [Compatibility Matrix](/compatibility-matrix) and [Multi-Tenancy](/multi-tenancy)
- Read the [Backend Architecture](/architecture), [RBAC and Role Levels](/rbac-and-role-levels), and [OpenAPI Workflow](/openapi-workflow)
- Use [Session and 2FA](/session-and-2fa) if you need the auth-session model, device management, and TOTP guarantees in one place
- Use [Audit and Compliance](/audit-and-compliance) when you need the repository's audit policy and retention model in one place
- Use [Deletion Lifecycle](/deletion-lifecycle) if you need the repository's disable-first, soft-delete, and purge model in one place
- Use [Testing](/testing) when changing contracts, tenancy, or runtime behavior
- Review [Realtime](/realtime) if you are pairing with the websocket-enabled frontend flows
- Choose between [Production Runtime](/production-runtime) and [Octane Runtime](/octane)
- Use [Release Artifacts](/release-artifacts) when you need the exact GHCR / SBOM / attestation consumption path
- Use the [Release Final Checklist](/release-final-checklist) before publishing
