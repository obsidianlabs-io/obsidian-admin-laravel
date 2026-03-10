# Backup/Restore Drill

Use this page when you want a real rehearsal, not just a backup command reference.

This checklist assumes:

- the source environment is already running
- you can create one disposable restore environment
- you are validating the database plus runtime together

## Goal

A successful drill proves all of these, in order:

1. the running environment can produce a usable SQL dump
2. a clean target environment can restore that dump
3. the restored runtime can bootstrap Laravel
4. live and ready probes still pass
5. the operator captures enough evidence to repeat the process

## Pre-flight

Before starting, capture:

- source environment name
- current backend release tag
- current frontend release tag, if paired
- database dump filename you plan to create
- target restore environment name
- operator name and timestamp

Do not run the drill directly against the primary production database.

## 1. Create the backup

From the backend repository:

```bash
scripts/ops/mysql-backup.sh build/backups/$(date +%F-%H%M%S)-drill.sql docker-compose.production.yml .env
```

Record:

- dump filename
- dump size
- source image tag

## 2. Prepare the restore target

Bring up a disposable runtime using either:

- `docker-compose.production.yml`
- `docker-compose.demo.yml`

The target must use separate volumes and a separate `.env` file from the source environment.

## 3. Restore the dump

```bash
scripts/ops/mysql-restore.sh build/backups/2026-03-10-180000-drill.sql docker-compose.production.yml .env.restore
```

If the target uses a different compose file, pass that compose file explicitly.

## 4. Run post-restore verification

Use the verification helper against the restored environment:

```bash
scripts/ops/post-restore-verify.sh https://restore-api.example.com docker-compose.production.yml .env.restore
```

This verifies:

- `GET /api/health/live`
- `GET /api/health/ready`
- `php artisan about --only=environment`
- health routes still registered inside the container

## 5. Run functional spot checks

Minimum functional checks after the verification helper passes:

1. seeded or evaluator login works
2. one tenant-scoped list endpoint returns data
3. one audit list endpoint returns data
4. one demo-safe save path still succeeds

If the drill is for backend-only operations, at least validate:

- authenticated login
- one tenant-scoped API list
- one audit API list

## 6. Rehearse image rollback

After restore verification, rehearse a runtime rollback:

1. switch the app image tag to the previous immutable GHCR version
2. restart the runtime
3. rerun `scripts/ops/post-restore-verify.sh`

This proves image rollback and data restore can be operated independently.

## 7. Capture evidence

Store these artifacts from every drill:

- SQL dump filename
- restore target env name
- health probe output
- `php artisan about --only=environment` output
- current image tag and rollback image tag
- operator notes on any manual fixes

Do not close the drill without storing evidence in your release or ops notes.

## 8. Pass / fail criteria

Pass:

- dump created successfully
- restore completes successfully
- verification helper passes
- functional spot checks pass
- rollback rehearsal passes

Fail:

- any probe fails
- artisan bootstrap fails
- restore requires undocumented manual intervention
- rollback requires undocumented manual intervention

## 9. Suggested cadence

Run the drill:

- before the first public evaluator demo
- once per active release line
- after major runtime or database changes
- after changing image publish or rollback strategy

## Related documents

- `docs/backup-and-recovery.md`
- `docs/production-runtime.md`
- `docs/release-artifacts.md`
- `docs/demo-deployment-runbook.md`
