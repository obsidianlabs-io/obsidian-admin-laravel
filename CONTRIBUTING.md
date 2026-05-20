# Contributing

Thanks for contributing to Obsidian Admin Laravel.

This repository is intended to be a production-grade Laravel 13 backend baseline. Contributions should improve correctness, maintainability, security, or operational clarity. Please avoid speculative abstractions and framework churn.

## Before You Start

- Use PHP `8.4`
- Use Composer `2.x`
- Prefer Docker if you need the full local stack (`MySQL + Redis + Horizon + Reverb`)
- Read `docs/architecture.md`
- Read `docs/release-sop.md` if your change affects release gates

## Local Setup

### Option A: Docker

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
docker compose -f docker-compose.dev.yml run --rm composer
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan key:generate
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force --seed
```

### Option B: Native PHP

```bash
git clone https://github.com/obsidianlabs-io/obsidian-admin-laravel.git
cd obsidian-admin-laravel
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Optional Octane Runtime

The repository now ships with official Laravel Octane integration. To initialize the local RoadRunner runtime:

```bash
composer run octane:install
composer run octane:start
```

Read `docs/octane.md` before changing Octane or RoadRunner behavior.

## Git Hooks

This project uses [Lefthook](https://github.com/evilmartians/lefthook) to run local quality checks before commits and pushes. Hooks are installed automatically when you run `composer install`. You can also install or refresh them manually:

```bash
composer run hooks:install
```

### Hook Stages

#### Pre-Commit (runs on `git commit`)

These checks run in parallel against staged files only:

| Check | Scope | What it does |
|-------|-------|--------------|
| PHP Lint | `*.php` | Syntax check via `php -l` |
| Pint Fix | `*.php` | Auto-formats and re-stages fixed files |
| JSON Syntax | `*.json` | Validates JSON parse-ability |
| YAML Syntax | `*.yaml`, `*.yml` | Validates YAML parse-ability |
| Secret Scan | all staged files | Detects credentials via gitleaks (skipped if not installed) |
| Large File Check | all staged files | Blocks files exceeding 5 MB |

#### Commit-Msg (runs on `git commit`)

| Check | What it does |
|-------|--------------|
| Conventional Commit | Validates message format: `<type>(<scope>)?: <description>` (max 72 chars) |

Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`. Merge commits are accepted automatically.

#### Pre-Push (runs on `git push`)

| Check | Composer script |
|-------|-----------------|
| Branch Guard | Blocks direct pushes to `main` and `release/*` |
| PHPStan | `composer run analyse` |
| Architecture | `composer run quality:architecture` |
| Docs Path Safety | `composer run docs:path-safety` |
| OpenAPI Lint | `composer run openapi:lint` |
| Contract Check | `composer run contract:check` |
| Security Check | `composer run security:check` |

### Bypassing Hooks

For emergency fixes or WIP commits, you can skip hooks:

```bash
# Skip pre-commit and commit-msg hooks
git commit --no-verify

# Skip pre-push hooks only
LEFTHOOK_EXCLUDE=pre-push git push
```

> Hooks complement but do NOT replace CI. CI remains the source of truth for merge eligibility.

For full details, troubleshooting, and how to add new checks, see [`docs/git-hooks.md`](docs/git-hooks.md).

## Development Rules

- Keep controllers thin: `FormRequest -> DTO -> Action/Service -> Resource/Response`
- Keep domain boundaries intact under `app/Domains/*`
- Do not introduce raw request payload arrays back into high-value controller or service boundaries
- Prefer typed DTOs and typed result/data objects over unstructured arrays
- Do not add Laravel-specific global state patterns that break long-lived worker safety
- Do not weaken tenant safety, role-level governance, or audit coverage to make tests pass
- Public-facing docs and GitHub templates must use repo-relative paths or public URLs, never machine-specific absolute paths

## Validation Checklist

Run the relevant gates before opening a pull request.

### Minimum backend gate

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
composer run docs:path-safety
```

### Recommended full backend gate

```bash
composer run quality:check
```

### Database matrix

If your change can affect query behavior, migrations, or database-specific SQL, run the database variants too:

```bash
composer run test:mysql
composer run test:pgsql
```

## API and Contract Changes

If you change request or response shapes:

```bash
php artisan api:contract-snapshot --write
php artisan openapi:lint
```

Keep the OpenAPI output and contract snapshot aligned with the actual controller surface.

## Pull Request Expectations

A good pull request should:

- have a narrow scope
- explain the problem and the chosen fix
- mention any schema, contract, queue, cache, or tenant-scope impact
- include tests when behavior changes
- avoid mixing refactors with unrelated formatting or cleanup

## What We Usually Reject

We are unlikely to accept pull requests that:

- replace coherent architecture with trend-driven rewrites
- introduce cross-domain shortcuts that bypass existing boundaries
- add framework coupling with little payoff
- weaken release gates, static analysis, or architecture tests
- add broad abstractions without a clear maintenance win
