# Demo Deployment Runbook

Use this page when you want the shortest executable path to launch the first hosted full-stack demo.

This runbook assumes:

- backend runtime uses `docker-compose.demo.yml`
- backend config starts from `.env.demo.example`
- frontend build starts from `.env.demo-live.example`
- the first rollout is evaluator-only or read-mostly, not a fully open writable public environment

## 1. Prepare the backend host

Minimum host capabilities:

- Docker Engine with Compose support
- one public HTTPS entry for the API
- private container networking for MySQL and Redis
- enough disk for images, database, Redis appendonly data, and logs

Recommended baseline:

- 2 vCPU
- 4 GB RAM
- persistent volume for database and Redis

## 2. Prepare backend configuration

On the backend host:

```bash
cp .env.demo.example .env.demo
```

Replace at least these values:

- `APP_URL`
- `REVERB_HOST`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- `APP_KEY`
- mail sender values if you do not want placeholder addresses

Do not reuse production secrets in the demo environment.

## 3. Start the backend runtime

```bash
docker compose -f docker-compose.demo.yml pull
docker compose -f docker-compose.demo.yml up -d
docker compose -f docker-compose.demo.yml exec app php artisan key:generate --force
docker compose -f docker-compose.demo.yml exec app php artisan migrate:fresh --seed --force
```

If you need a non-destructive update after the first seed, replace `migrate:fresh --seed` with:

```bash
docker compose -f docker-compose.demo.yml exec app php artisan migrate --force
```

## 4. Verify the backend

Use these checks before pairing the frontend:

```bash
curl --fail --silent https://demo-api.example.com/api/health/live
curl --fail --silent https://demo-api.example.com/api/health/ready
docker compose -f docker-compose.demo.yml exec app php artisan about --only=environment
```

Expected result:

- live returns `status: alive`
- ready returns success
- artisan bootstrap succeeds inside the running container

## 5. Build the frontend against the live backend

In the frontend repository:

```bash
cp .env.demo-live.example .env.demo-live
```

Set these values to the demo backend:

- `VITE_SERVICE_BASE_URL`
- `VITE_OTHER_SERVICE_BASE_URL`
- `VITE_REVERB_SCHEME`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_APP_KEY`

Then build:

```bash
pnpm install
pnpm build --mode demo-live
```

Deploy the generated `dist` bundle to your static host.

## 6. Verify the full stack

Before sharing the environment externally, run these checks:

### Backend repository

```bash
php artisan openapi:lint
php artisan security:baseline
```

### Frontend repository

```bash
pnpm typecheck:api
pnpm test:fullstack
```

### Browser-level sanity

Confirm manually:

- login works with seeded demo credentials
- tenant switching works
- user list loads
- audit list loads
- selected writable demo drawers still save correctly

## 7. Reset policy

For the first hosted demo, use one of these two policies:

- evaluator demo: `migrate:fresh --seed` on every controlled redeploy
- read-mostly public demo: nightly `migrate:fresh --seed`

If you expose low-risk writable flows publicly, add an hourly cleanup for known demo-only records.

## 8. Rollback

Use immutable backend image tags and immutable frontend build artifacts.

Rollback sequence:

1. redeploy the previous frontend bundle
2. switch `APP_RUNTIME_IMAGE` in the backend environment to the previous GHCR tag
3. restart the backend stack
4. rerun live and ready probes

If the issue is data-related rather than image-related:

1. stop public access temporarily
2. run `migrate:fresh --seed`
3. restore the previous frontend and backend versions only if code drift is involved

## 9. First public promotion checklist

Do not share the demo URL until all of these are true:

- backend docs site is public
- frontend docs site is public
- frontend preview is public
- full-stack pairing smoke is green
- backend runtime health probes are green
- the demo reset policy is documented
- the seeded credentials and access policy are documented
