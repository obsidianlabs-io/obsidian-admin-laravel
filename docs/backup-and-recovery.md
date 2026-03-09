# Backup and Recovery

Use this page when you need a repeatable backup, restore, and release rollback path for production or evaluator environments.

## Scope

This repository treats backup and rollback as three separate concerns:

- database backup and restore
- runtime image rollback
- demo reset workflows

Do not treat `migrate:fresh --seed` as a production recovery tool.

## 1. Database backup

For Docker-based environments, use the provided helper:

```bash
scripts/ops/mysql-backup.sh build/backups/$(date +%F-%H%M%S)-app.sql docker-compose.production.yml .env
```

Arguments:

1. output SQL path
2. compose file (optional, defaults to `docker-compose.production.yml`)
3. env file (optional, defaults to `.env`)

The helper executes `mysqldump` inside the running MySQL container and writes a plain SQL dump on the host.

## 2. Database restore

Restore a dump back into the running MySQL service with:

```bash
scripts/ops/mysql-restore.sh build/backups/2026-03-10-180000-app.sql docker-compose.production.yml .env
```

Use restore only when you are explicitly recovering data. For evaluator demos, prefer a full reset instead.

## 3. Release rollback

Code rollback and data rollback are different operations.

Use runtime rollback when:

- the published image is bad
- a migration has not irreversibly changed data
- the problem is application behavior, not corrupted data

Recommended sequence:

1. switch the app image tag back to the previous immutable GHCR version
2. redeploy the stack
3. run `GET /api/health/live`
4. run `GET /api/health/ready`
5. execute `php artisan about --only=environment`

If the issue is data corruption or destructive migration behavior, restore the database before reopening traffic.

## 4. Demo reset

For evaluator or hosted demo environments, do not mix production recovery with demo cleanup.

Use one of these paths instead:

- `php artisan migrate:fresh --seed --force`
- restore a known-clean demo SQL snapshot

## 5. Recommended drill cadence

Run a real recovery drill at least once per release line:

1. create a SQL backup
2. restore it into a disposable environment
3. verify health endpoints
4. verify seeded login and one write-safe flow
5. rehearse a GHCR image rollback

## 6. What to verify after restore

Minimum checks:

- `GET /api/health/live`
- `GET /api/health/ready`
- `php artisan about --only=environment`
- queue worker starts
- seeded login works
- tenant-scoped list endpoints return expected data

## 7. Related documents

- `docs/production-runtime.md`
- `docs/release-artifacts.md`
- `docs/demo-deployment-runbook.md`
- `docs/deletion-lifecycle.md`
