# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

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
