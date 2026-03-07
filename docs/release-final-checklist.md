# Release Final Checklist

This is the final release gate for `/Users/zero/Documents/Project/WK/obsidian-admin-laravel`.

Use this checklist after feature work is finished and before creating or publishing a GitHub Release.

## 1. Working Tree

- `git status --short` is empty
- `HEAD` is the exact commit you want to release
- no local hotfixes are waiting outside `main`

## 2. Release Content

- `CHANGELOG.md` is updated
- the target release note exists:
  - `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/releases/vX.Y.Z.md`
- repository metadata still matches the current product positioning:
  - `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/github/repository-metadata.md`

## 3. Backend Quality Gates

All of these must pass on the release commit:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

If your release touches platform hardening, infrastructure, or contract surfaces, also run:

```bash
php artisan openapi:lint
php artisan security:baseline
php artisan http:proxy-trust-check --strict
```

## 4. Runtime Truth

Confirm the README still matches the repository reality:

- Octane claims match actual package/config state
- queue guidance matches current Horizon/Redis requirements
- Docker guidance matches current compose files
- release docs do not promise disabled or placeholder features

## 5. GitHub Repository Settings

Confirm there is no drift in:

- About / Description / Topics
- branch protection on `main`
- required status checks
- Actions permissions
- secret / variable assumptions
- `CODEOWNERS`

Reference:

- `/Users/zero/Documents/Project/WK/obsidian-admin-laravel/docs/github/repository-setup-checklist.md`

## 6. Push Order

Always use this order:

1. push `main`
2. confirm CI is green on remote
3. create annotated tag
4. push tag
5. publish GitHub Release

## 7. Tag Rules

- use annotated tags only
- tag must point to the code release commit
- do not move an existing release tag just to include late docs

## 8. GitHub Release

Before pressing publish:

- selected tag is correct
- release title matches repository metadata guidance
- release body comes from the prepared release note
- release version matches `CHANGELOG.md`

## 9. Post-Release Check

After publishing, confirm:

- tag exists remotely
- GitHub Release is visible
- `main` is still green
- no new workflow failures appeared on tag push

## 10. Hard Stop Conditions

Do not release if any of these are true:

- working tree is dirty
- required quality gates are red
- release note and changelog disagree on version
- tag points at the wrong commit
- README is advertising features that are not actually wired
