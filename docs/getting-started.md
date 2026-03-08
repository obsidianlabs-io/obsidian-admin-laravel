# Getting Started

## Requirements

- `PHP 8.4`
- `Composer 2`
- `Docker` for the recommended local stack
- `MySQL` / `PostgreSQL` / `SQLite` depending on your runtime mode
- `Redis` for queue, cache, and Horizon-oriented workflows

## Recommended quick start

For the lowest-friction local path, use Docker development:

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env

docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

## Native local path

If you want to run the app directly on your machine:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

## Quality gates

Before you trust a local change, at minimum run:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

## Runtime options

This repository supports multiple runtime shapes:

- Docker development: `docker-compose.dev.yml`
- Production-like php-fpm stack: `docker-compose.production.yml`
- Octane + RoadRunner stack: `docker-compose.octane.yml`

Use these docs next:

- `docs/production-runtime.md`
- `docs/octane.md`
