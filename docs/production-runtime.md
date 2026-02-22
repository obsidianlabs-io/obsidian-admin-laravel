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
