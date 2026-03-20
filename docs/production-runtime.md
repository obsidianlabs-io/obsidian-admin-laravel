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

## 1.5) GHCR Release Image

Stable release tags publish a multi-arch app image to:

- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:<tag>`

Supported platforms:

- `linux/amd64`
- `linux/arm64`

Tag strategy:

- `1.2.0`: immutable full release tag
- `1.2`: latest patch in the current minor line
- `1`: latest stable minor in the current major line
- `latest`: stable non-prerelease release only

Use the fully versioned tag for production rollout and rollback control.

Pull example:

```bash
docker pull ghcr.io/obsidianlabs-io/obsidian-admin-laravel:v1.3.0
```

Minimal container boot example:

```bash
docker run --rm -p 8080:8000 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:Q2qE5A3yM4tQvL3X0yr7M5m4r2m40fX9zCw1Q2m3N4o= \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e AUDIT_QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  -e LOG_STACK=stderr \
  ghcr.io/obsidianlabs-io/obsidian-admin-laravel:v1.3.0
```

This minimal run path is only meant to validate image boot and health. For database-backed production usage, prefer `docker-compose.production.yml` or `docker-compose.octane.yml`.

Compose consumption example:

```yaml
services:
  app:
    image: ghcr.io/obsidianlabs-io/obsidian-admin-laravel:1.3.0
    pull_policy: always
```

Launch with:

```bash
docker compose -f docker-compose.production.yml -f docker-compose.image.yml up -d
```

This lets you keep the repository-provided `nginx/mysql/redis/horizon/scheduler` topology while consuming a published immutable app image.

Release workflow verification notes:

- the published GHCR app image is a PHP-FPM runtime image, so the release workflow validates cold boot by checking container startup and running `php artisan about --only=environment`
- route-level HTTP health checks still require an edge/runtime topology such as `docker-compose.production.yml` or `docker-compose.octane.yml`

If you want to probe the published image through the real HTTP path, use the published app image with the compose override above and then call:

```bash
curl http://127.0.0.1:8080/api/health/live
```

That route-level probe is already covered in CI by the production Docker smoke job.

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
