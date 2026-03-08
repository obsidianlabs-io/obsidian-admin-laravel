# Security Baseline

The `security:baseline` command is the repository's fast security gate for deployment-critical configuration and API protection coverage. It is not a generic vulnerability scanner. It answers a narrower question: "Is this application instance configured to meet the minimum runtime security expectations for this project?"

Use it before release, in CI, and after environment-level changes.

## What it checks

The baseline currently validates controls such as:

- application crypto bootstrap, including `APP_KEY`
- login throttling and password policy minimums
- production hardening flags
- auth middleware coverage for non-public API routes
- permission middleware coverage for management APIs
- trusted proxy and request hardening configuration
- optional project policy requirements such as super-admin 2FA

The exact checks are intentionally opinionated. They are designed around this repository's runtime model, not around every possible Laravel deployment style.

## Pass, fail, and warn

The command distinguishes between:

- **fail**: the application is below the minimum baseline and should not be treated as release-ready
- **warn**: the runtime is technically valid, but an expected hardening control is weaker than the repository's preferred standard

Typical examples:

- missing `APP_KEY` is a **fail**
- invalid proxy header configuration is a **fail**
- a project that does not require super-admin 2FA may still produce a **warn** when that policy is unset

This distinction matters because the command is meant to support both strict enterprise rollouts and lighter internal deployments without lying about risk.

## Recommended command usage

Run locally before release work:

```bash
php artisan security:baseline
```

Run in strict mode when validating a production-ready posture:

```bash
php artisan security:baseline --strict
```

Use CI to enforce the baseline before tags and production deploys.

## How it fits with other security checks

`security:baseline` is one layer of the repository's security model:

1. `security:baseline`
   Confirms runtime configuration and route-coverage expectations.
2. feature and architecture tests
   Confirm behavior such as tenant boundaries, auth/session rules, and API hardening.
3. supply-chain workflow
   Confirms dependency review, audit, SBOM generation, runtime image scanning, and attestations.

It should be used together with:

- [Security Checklist](/security-checklist)
- [Session and 2FA](/session-and-2fa)
- [Audit and Compliance](/audit-and-compliance)
- [Testing](/testing)

## Practical CI order

For a release-grade backend pipeline, use this order:

1. `php artisan openapi:lint`
2. `php artisan security:baseline --strict`
3. `php artisan api:contract-snapshot`
4. `php artisan test`

This keeps contract drift, runtime hardening drift, and behavior regressions visible as separate failure modes.

## What this command is not

It is not a replacement for:

- penetration testing
- infrastructure scanning
- secret scanning
- SAST/DAST tooling
- dependency audits
- container vulnerability scanning of external base images outside this repository's own build pipeline

The repository handles runtime image vulnerability scanning separately through GitHub Actions:

- `Backend Supply Chain` scans the locally built runtime image on pull requests, `main`, and nightly schedule
- `Release` scans the published GHCR image after tag push

Treat it as a repository-native security gate, not as your entire security program.
