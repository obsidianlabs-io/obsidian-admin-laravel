# Health Model

This repository treats health checks as layered signals, not as one generic "status endpoint".

That distinction matters because a container can be running while:

- Laravel bootstrap is broken
- HTTP routing is broken
- production guards are failing
- the database is unavailable

## 1. The three HTTP health endpoints

The backend exposes:

- `GET /api/health/live`
- `GET /api/health/ready`
- `GET /api/health`

Use them for different purposes.

## 2. Liveness

`/api/health/live` answers the narrow question:

- is the application process alive enough to respond?

This is the fastest probe and is appropriate for:

- container health checks
- load balancer liveness
- runtime smoke validation

It is not meant to prove the whole system is ready for traffic.

## 3. Readiness

`/api/health/ready` answers the stronger question:

- is the application ready to serve normal traffic?

This is the probe to use for:

- rollout gates
- orchestrator readiness checks
- blue/green or canary promotion decisions

If a critical dependency fails, readiness should fail before you route normal traffic.

## 4. Full diagnostics

`/api/health` is the richer operational endpoint.

It includes:

- top-level status
- per-check results
- contextual runtime information

Use it for:

- operator diagnosis
- post-deploy review
- debugging degraded readiness

Do not confuse it with the liveness probe.

## 5. Non-HTTP probe layers

This repository deliberately uses more than HTTP health endpoints.

Runtime confidence is built from multiple layers:

1. image or container boot
2. Laravel bootstrap via Artisan
3. route-level liveness
4. readiness checks

That is why CI includes:

- image boot smoke
- docker smoke
- octane smoke

Each one proves a different failure boundary.

## 6. What the service checks

The health system already models individual checks such as:

- app key presence
- database connectivity
- production guard expectations

Representative implementation:

- `app/Domains/System/Services/HealthStatusService.php`
- `app/Domains/System/Http/Controllers/HealthController.php`

## 7. How to use probes in practice

Recommended operator usage:

- use `/api/health/live` for fast runtime probes
- use `/api/health/ready` for deployment gating
- use `/api/health` when diagnosing why readiness is not green

That separation keeps probes useful instead of forcing one endpoint to serve every purpose poorly.

## 8. Relationship to CI

CI does not rely on one health check only.

Examples:

- image release verification proves artifact boot and Laravel bootstrap
- docker smoke proves route-level HTTP liveness in the production topology
- octane smoke proves the RoadRunner runtime path can boot and answer live probes

This layered model is intentional and should be preserved.

## 9. Where to look next

- [Testing](/testing)
- [Runtime Topology](/runtime-topology)
- [Production Runtime](/production-runtime)
- [Octane Runtime](/octane)
- [Release Final Checklist](/release-final-checklist)
