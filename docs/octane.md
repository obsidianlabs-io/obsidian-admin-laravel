# Octane Support

`Obsidian Admin Laravel` now ships with official `laravel/octane` integration and RoadRunner-oriented defaults.

What is included in the repository:

- `laravel/octane` in `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/composer.json`
- RoadRunner PHP dependencies:
  - `spiral/roadrunner-http`
  - `spiral/roadrunner-cli`
- `config/octane.php`
- `php artisan octane:*` commands
- application-specific worker reset logic via `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Listeners/Octane/PrepareObsidianRequestState.php`

## Important distinction

The repository includes the Octane package and configuration, but the RoadRunner binary itself is still machine-specific.

That means:

- the project is in a real Octane installation state
- a fresh clone still needs a local RoadRunner binary before `octane:start` can run
- the binary should stay local and is intentionally ignored via `.gitignore`

Ignored local runtime files:

- `rr`
- `.rr.yaml`

## First-time local setup

After `composer install`, initialize the local RoadRunner runtime once:

```bash
php artisan octane:install --server=roadrunner
```

Then start Octane:

```bash
php artisan octane:start --server=roadrunner
```

You can also use the Composer shortcuts committed in the project:

```bash
composer run octane:install
composer run octane:start
```

## What is already hardened for long-lived workers

The codebase includes explicit request-state reset handling for Octane workers:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Listeners/Octane/PrepareObsidianRequestState.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Http/Middleware/AssignRequestId.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Http/Middleware/SetRequestLocale.php`
- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/app/Support/ApiDateTime.php`

## Current support model

The default Docker stack still runs the API behind `php-fpm`.

That is intentional:

- Docker remains the lowest-friction default for contributors
- Octane is available as an explicit runtime choice
- production teams can decide whether they want `php-fpm` or `Octane + RoadRunner`

## Recommended wording

Use these descriptions when documenting the project:

- `ships with official Laravel Octane integration`
- `RoadRunner-oriented Octane setup`
- `worker-safe request lifecycle guards`

Avoid saying:

- `RoadRunner binary is committed in the repository`
- `Docker runs Octane by default`
