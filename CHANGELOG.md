# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.2.0] - 2026-03-07

### ✨ Added
- Introduced typed request, action, service, and response boundaries across `Auth`, `Access`, `Tenant`, `Shared`, and `System` domains.
- Added query action layer for high-value list endpoints and shared pagination payload builders.
- Added architecture guard tests for controller/service boundary rules.
- Added PostgreSQL CI support and improved quality workflow stability.

### 🔧 Changed
- Replaced remaining controller array contexts with DTOs, typed result objects, and explicit action orchestration.
- Unified session projection, idempotency state handling, CRUD schema delivery, OpenAPI inspection, and tenant option payloads behind typed data objects.
- Tightened auth/session behavior for inactive tenants, roles, organizations, and teams.
- Improved deletion lifecycle, audit log classification, API access log safety, and platform hardening flows.

### 🐞 Fixed
- Fixed multiple quality workflow issues around CI contract checks and conditional execution.
- Fixed backend boundary drift by locking high-value service and controller return types with architecture tests.

## [1.1.0] - 2026-03-03

### ✨ Added
- Tenant-scoped **Organization** and **Team** modules, including CRUD APIs, DTOs, request validation, policies, services, and resources.
- User creation/update now supports `organizationId` and `teamId` binding with server-side consistency checks.
- New database schema for `organizations`, `teams`, and user relation fields (`organization_id`, `team_id`).

### 🔧 Changed
- Menu metadata and route rules now include organization/team entries with strict tenant visibility.
- Default seed data now includes organization/team records and user bindings.
- API contract and static-analysis baselines were refreshed for release gate stability.

### 🐞 Fixed
- Resolved create-user DTO argument mismatch introduced by organization/team binding expansion.
- Resolved frontend-backend contract drift for role query (`manageableOnly`) and new organization/team API surfaces.

## [1.0.0] - 2026-02-23

### 🎉 Initial Public Release (Obsidian Admin Laravel)

Welcome to the first official release of **Obsidian Admin Laravel**, an enterprise-grade API backend built for strictly-typed, high-performance monolith applications. This template breaks away from bloated MVC patterns by natively implementing Domain-Driven Design (DDD), strict architectural boundaries, and native OpenAPI generation.

### ✨ Features
- **Architecture**: Laravel 12 on PHP 8.2+.
- **Performance Constraints**: Native, pre-configured support for **Laravel Octane via RoadRunner**, allowing maximum throughput for API endpoints.
- **Domain-Driven Design (DDD)**: Discarded traditional `app/Http/Controllers` structures for deep, self-contained `app/Domains` boundaries (e.g., Auth, Tenant, System).
- **Physical Boundary Enforcement**: Deep integration with `qossmic/deptrac` ensuring that Domains cannot accidentally leak into or depend on unauthorized Domain layers.
- **Strict Data Transfer Objects (DTOs)**: Removed unstructured Array requests in favor of PHP 8.2 readonly DTO classes to guarantee type-safety between Controllers and domain Services.
- **Native OpenAPI Spec Generation**: Real-time integration with `dedoc/scramble` automatically parses Controller AST and generating an immaculate `docs/openapi.yaml` without requiring a single manual `@OA` docblock annotation.
- **Multi-Tenant System**: Built-in support for multiple SaaS organizations using Global Scopes, strictly isolated from Platform-level operations.
- **Event-Driven Audit Logging**: Highly scalable audit logging system pushed completely into queued event listeners via Laravel Horizon / Redis to avoid punishing API response times.
- **Real-Time WebSockets**: Natively configured `laravel/reverb` broadcasting global `SystemRealtimeUpdated` events straight to connected frontends.
- **Schema-Driven UI Controller**: Built an internal `CrudSchemaController` capable of streaming JSON schema definitions directly into downstream Vue applications to auto-generate forms and tables.
- **Extensive Test Coverage**: Rigorous unit, feature, and architecture tests implemented via `pestphp/pest`.

*This repository provides the ultimate foundation for scaling monolithic Laravel infrastructure.*
