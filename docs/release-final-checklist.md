# Release Final Checklist

This is the last sign-off checklist for `obsidian-admin-laravel`.

Use it after implementation is finished and before publishing a release.

## 1. Working Tree

- `git status --short` is empty
- `HEAD` is the exact commit you want to release
- no local-only fixes are waiting outside `main`

## 2. Release Content

- `CHANGELOG.md` is updated
- the target release note exists:
  - `docs/releases/vX.Y.Z.md`
- repository metadata still matches current positioning:
  - `docs/github/repository-metadata.md`

## 3. Required Backend Gates

All of these must pass on the release commit:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
```

- `Backend Supply Chain` is green
- the `backend-sbom-cyclonedx` artifact exists for the release commit
- the `backend-runtime-image-scan` artifact exists for the release commit
- the SBOM artifact has an attestation on the push workflow
- the `backend-release-image-scan` artifact exists for the release tag workflow

If the release touched infrastructure, platform hardening, or contract surfaces, also run:

```bash
php artisan openapi:lint
php artisan security:baseline
php artisan http:proxy-trust-check --strict
php artisan octane:start --server=roadrunner
curl --fail --silent http://127.0.0.1:8000/api/health/live
php artisan octane:stop --server=roadrunner
```

If the release touched Docker, runtime images, compose files, or PHP extensions, also run:

```bash
APP_HTTP_PORT=18080 REVERB_PUBLIC_PORT=16001 docker compose -f docker-compose.production.yml up -d --build mysql redis app nginx
curl --fail --silent http://127.0.0.1:18080/api/health/live
docker compose -f docker-compose.production.yml down -v
```

If the release changed the final app image, entrypoint, or PHP-FPM startup path, also run:

```bash
docker build -t obsidian-admin-laravel:image-smoke .
cat <<'PHP' > /tmp/image-smoke-probe.php
<?php
require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/health/live', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent() ?: '';

fwrite(STDOUT, $content.PHP_EOL);

$kernel->terminate($request, $response);

if ($response->getStatusCode() !== 200 || !str_contains($content, '"status":"alive"')) {
    fwrite(STDERR, "live probe failed\n");
    exit(1);
}
PHP
docker run -d --name obsidian-admin-laravel-image-smoke \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_KEY=base64:Q2qE5A3yM4tQvL3X0yr7M5m4r2m40fX9zCw1Q2m3N4o= \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e AUDIT_QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  -e LOG_STACK=stderr \
  obsidian-admin-laravel:image-smoke
docker inspect -f '{{.State.Running}}' obsidian-admin-laravel-image-smoke
docker cp /tmp/image-smoke-probe.php obsidian-admin-laravel-image-smoke:/tmp/image-smoke-probe.php
docker exec obsidian-admin-laravel-image-smoke php /tmp/image-smoke-probe.php
docker rm -f obsidian-admin-laravel-image-smoke
docker image rm obsidian-admin-laravel:image-smoke
```

## 4. Runtime Truth

Confirm documentation still matches reality:

- Octane claims match actual package/config state
- queue guidance matches Horizon + Redis requirements
- Docker guidance matches current compose files
- README does not advertise disabled or placeholder features as fully implemented

## 5. GitHub Settings

Confirm there is no drift in:

- About / Description / Topics
- branch protection on `main`
- required status checks
- Actions permissions
- secret / variable assumptions
- `CODEOWNERS`

Reference:

- `docs/github/repository-setup-checklist.md`

## 6. Push Order

Always use this order:

1. push `main`
2. confirm remote CI is green
3. create annotated tag
4. push tag
5. publish GitHub Release

## 7. Tag Rules

- use annotated tags only
- tag must point to the code release commit
- do not move an existing release tag just to include late docs

## 8. GitHub Release

Before publishing:

- selected tag is correct
- release title matches repository metadata guidance
- release body comes from the prepared release note
- release version matches `CHANGELOG.md`
- the release tag is expected to publish `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:vX.Y.Z`
- the GHCR release image is expected to be multi-arch: `linux/amd64` and `linux/arm64`
- `docs/releases/vX.Y.Z.md` exists if you want the release workflow to publish the exact curated note

## 9. Post-Release Check

After publishing, confirm:

- tag exists remotely
- GitHub Release is visible
- GHCR package shows the expected version tag
- GHCR package manifest includes `linux/amd64` and `linux/arm64`
- the published image vulnerability scan job is green
- `main` is still green
- no new workflow failure appeared on tag push

## 10. Hard Stop Conditions

Do not release if any of these are true:

- working tree is dirty
- required gates are red
- release note and changelog disagree on version
- tag points to the wrong commit
- README is ahead of actual implementation
