# Full-Stack Evaluation

Use this page when you want the shortest credible path to evaluate the backend alone, the frontend alone, or the full paired stack.

## Option 1. Frontend-only evaluation

If you only want to inspect the admin UX and generated-contract-driven frontend shape first, start with:

- docs: [Obsidian Admin Vue](https://obsidianlabs-io.github.io/obsidian-admin-vue/)
- preview: [https://obsidianlabs-io.github.io/obsidian-admin-vue/preview/](https://obsidianlabs-io.github.io/obsidian-admin-vue/preview/)

This mode does not require a running Laravel instance.

## Option 2. Backend-only evaluation

Use this when you want to verify the Laravel runtime, tenant model, OpenAPI contract, release image path, and CI/runtime posture first.

Fastest local path:

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

Then probe:

```bash
curl http://127.0.0.1:8080/api/health/live
```

## Option 3. Full local pairing

Use this when you want to validate the real frontend/backend contract relationship.

### Start the backend

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

### Start the frontend

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-vue.git
cd obsidian-admin-vue
pnpm install
pnpm dev
```

### Verify frontend contract sync

In the frontend repository:

```bash
pnpm typecheck:api
```

If this command fails, do not treat the current pair as validated until generated API files are synced.

## Recommended evaluation order

Use this order if you are evaluating adoption:

1. frontend public preview
2. backend runtime and docs
3. full local pairing
4. release artifact and supply-chain inspection

This keeps setup cost low while still giving you a real view of the stack's architecture and operating model.
