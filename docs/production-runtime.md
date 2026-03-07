# Production Runtime Profile

This profile is designed for backend-only deployment with queue and scheduler separation.

## 1) Runtime Topology

- `nginx`: edge HTTP server
- `app`: PHP-FPM API service
- `horizon`: Redis queue consumer supervisor
- `scheduler`: Laravel scheduler loop
- `pulse-worker`: ingest worker for Pulse stream processing
- `mysql`: relational database
- `redis`: cache + queue backend (recommended)

Use `docker-compose.production.yml` for baseline orchestration.

Production compose only publishes edge ports by default:

- `nginx` on `8080` by default, configurable via `APP_HTTP_PORT`
- `reverb` on `6001` by default, configurable via `REVERB_PUBLIC_PORT`

`mysql` and `redis` stay internal to the Docker network unless you explicitly add host port bindings.

## 2) Start Stack

```bash
docker compose -f docker-compose.production.yml up -d --build
```

Then initialize the app:

```bash
docker compose -f docker-compose.production.yml exec app php artisan key:generate
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan db:seed --force
```

## 3) Health Probes

- Liveness: `GET /api/health/live`
- Readiness: `GET /api/health/ready`
- Full diagnostics: `GET /api/health`

Readiness returns `503` only when critical checks fail.

## 4) Supervisor Alternative (VM/Bare Metal)

Supervisor templates are included:

- `deploy/supervisor/laravel-queue-worker.conf`
- `deploy/supervisor/laravel-horizon.conf`
- `deploy/supervisor/laravel-scheduler.conf`
- `deploy/supervisor/laravel-pulse-worker.conf`

Use these when you are not deploying with Docker.
