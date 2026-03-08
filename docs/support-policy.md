# Support Policy

This page defines the practical support expectations for `obsidian-admin-laravel` as an open-source backend baseline.

## Scope

This repository is maintained for:

- enterprise admin backends
- SaaS control planes
- contract-driven pairing with `obsidian-admin-vue`
- long-lived Laravel 12 deployments that follow the documented runtime paths

This repository is not maintained as:

- a private consulting channel
- an SLA-backed production support desk
- a compatibility layer for heavily diverged forks
- a guarantee that every historical release pair stays supported forever

## Supported Release Lanes

The source of truth for supported backend/frontend combinations is:

- [`Compatibility Matrix`](./compatibility-matrix.md)

Current policy:

- the documented stable release pair receives best-effort bug triage and security fixes
- `main` paired with `main` is the active development lane and may change without backward-compatibility guarantees
- prerelease tags, stale forks, and unsupported version pairs may be asked to upgrade before issues are triaged

## Support Window

Best-effort support is focused on:

- the latest documented stable release pair
- the current `main` branch when the issue is reproducible there

Older release tags may still receive guidance, but they should not be treated as an actively maintained support target unless they remain the current stable pair in the compatibility matrix.

## Security Fix Policy

Security issues should follow [`SECURITY.md`](https://github.com/obsidianlabs-io/obsidian-admin-laravel/blob/main/SECURITY.md), not public issues.

For public releases:

- critical security fixes take priority over feature requests
- fixes are expected to land on the active support lane first
- backport decisions are case-by-case and depend on maintenance cost and risk

## Demo, Preview, and Evaluation Environments

Hosted demos, evaluator stacks, and preview environments are for product evaluation only.

They do not guarantee:

- production-grade uptime
- backward-compatible seeded data
- tenant persistence between resets
- support for destructive or high-volume workloads

Use them to validate fit, not as a long-term hosted service.

## What Maintainers Need From Reporters

Before opening an issue, reporters should:

1. confirm the problem on the latest stable pair or current `main`
2. include the exact backend tag or commit
3. include the paired frontend tag or commit when relevant
4. state the runtime path used:
   - `docker-compose.dev.yml`
   - `docker-compose.production.yml`
   - `docker-compose.octane.yml`
   - native PHP / custom deployment
5. include the relevant gate output:
   - `composer run quality:check`
   - `composer run test`
   - `php artisan openapi:lint`
   - runtime or smoke output when applicable

## Response Expectations

Maintainer response is best-effort.

In practice:

- reproducible bugs on active support lanes get priority
- security reports go through the private channel in `SECURITY.md`
- incomplete reports may be closed until more context is provided
- architecture questions are welcome, but design work for private downstream products is out of scope

## Rule of Thumb

If you need guaranteed timelines, incident handling, or bespoke delivery, do not treat GitHub Issues as an SLA-backed support channel.
