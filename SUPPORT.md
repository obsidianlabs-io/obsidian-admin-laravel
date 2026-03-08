# Support

This document explains how to get help for `obsidian-admin-laravel`.

## Scope

This repository is maintained as an open-source Laravel admin backend baseline.

Support is best-effort for:

- installation and local setup issues
- CI, Docker, Octane, RoadRunner, and environment bootstrap issues
- reproducible bugs in the current `main` branch
- contract-generation and OpenAPI workflow issues
- documentation gaps

Support is not intended for:

- private consulting
- project-specific feature delivery
- urgent production incident response
- long-diverged private forks

## Before Opening An Issue

Please confirm all of the following first:

1. You are testing against the latest `main` branch or the latest release tag.
2. You have read `README.md`.
3. You have read `CONTRIBUTING.md`.
4. You ran the baseline backend gates:

```bash
composer validate --strict --no-check-publish
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

5. If the problem is Docker or runtime related, confirm which path you used:

- `docker-compose.dev.yml`
- `docker-compose.production.yml`
- `php artisan octane:start`

## Support Channels

- Bug reports:
  [GitHub Issues](https://github.com/obsidianlabs-io/obsidian-admin-laravel/issues)
- Security reports:
  See `SECURITY.md`
- Contribution process:
  See `CONTRIBUTING.md`

## How To Ask For Help Well

Include:

- exact command you ran
- full error output
- PHP version
- Composer version
- database driver
- queue/cache driver
- whether you are using Docker, PHP-FPM, or Octane
- whether Redis is enabled
- whether the issue is reproducible on a fresh database

If the issue is contract-related, also include:

- backend commit or tag
- whether `docs/openapi.yaml` changed
- whether `docs/api-contract.snapshot` changed

## Response Expectations

For public issue support:

- triage is best-effort
- reproducible bugs get priority
- incomplete reports may be closed until more details are provided

If you need guaranteed timelines or operational support, do not treat GitHub Issues as an SLA-backed support channel.
