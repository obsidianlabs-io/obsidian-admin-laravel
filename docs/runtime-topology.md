# Runtime Topology

This repository supports more than one runtime path, but the topology is always explicit.

The goal is to make it obvious which processes own:

- HTTP entry
- Laravel request handling
- queues
- scheduling
- realtime broadcasting
- cache and database state

## 1. Default production topology

The baseline production path is the `docker-compose.production.yml` stack.

Core services:

- `nginx`: edge HTTP server
- `app`: Laravel API via PHP-FPM
- `mysql`: relational database
- `redis`: cache, queues, and transient runtime state
- `horizon`: queue processing
- `scheduler`: scheduled command loop
- `pulse-worker`: Pulse ingestion worker
- `reverb`: websocket/broadcast runtime

This path is the default because it is the least surprising for contributors and operators.

## 2. Octane topology

The alternative runtime path is `docker-compose.octane.yml`.

Core services:

- `octane`: Laravel Octane + RoadRunner HTTP runtime
- `mysql`
- `redis`
- `horizon`
- `scheduler`
- `pulse-worker`
- `reverb`

The main difference is that PHP-FPM and nginx are replaced by the long-lived Octane worker runtime.

Use this path when you explicitly want:

- RoadRunner-based HTTP serving
- long-lived worker performance
- runtime parity with Octane-specific deployments

## 3. Why the two topologies both exist

This repository does not force Octane everywhere.

That is intentional:

- `php-fpm + nginx` remains the lowest-friction default
- `Octane + RoadRunner` is available as an explicit runtime choice
- both paths are covered by CI smokes so they stay honest

This keeps the project practical for contributors while still supporting high-performance deployments.

## 4. Reverb in the topology

Realtime is kept as a separate runtime concern.

`reverb` is not collapsed into the HTTP process because that would make local and production behavior harder to reason about.

The expected model is:

- Laravel app dispatches broadcast events
- Reverb handles websocket fan-out
- frontend clients connect to the Reverb endpoint

That separation keeps the topology understandable and closer to real deployment behavior.

## 5. Queue and scheduler separation

Queue and scheduler responsibilities are not hidden inside the web process.

This repository keeps them explicit:

- `horizon` owns queue execution
- `scheduler` owns `schedule:run`
- `pulse-worker` owns Pulse stream ingestion

That makes failure modes easier to isolate and keeps runtime observability clearer.

## 6. Health probe expectations

Different topology layers support different levels of health checking:

- container boot probes confirm the runtime process starts
- Laravel bootstrap probes confirm Artisan can initialize the app
- route-level probes confirm HTTP entry and Laravel request handling are live

That is why CI includes multiple smokes instead of a single generic check.

## 7. Recommended operator mental model

Think about runtime in layers:

1. artifact integrity
2. process boot
3. Laravel bootstrap
4. HTTP health
5. queue/realtime/scheduler behavior

If you treat runtime as a single opaque container, debugging gets harder fast.

## 8. Where to look next

- [Production Runtime](/production-runtime)
- [Octane Runtime](/octane)
- [Realtime](/realtime)
- [Release Artifacts](/release-artifacts)
- [Testing](/testing)
