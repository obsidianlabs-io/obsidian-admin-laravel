# Feature Flags

## Current model

This repository uses Laravel Pennant as the foundation for feature control, with additional project-specific scope handling for admin use cases.

Key files:

- `app/Domains/System/Services/FeatureFlagService.php`
- `app/Domains/System/Actions/FeatureFlag`
- `app/Domains/System/Http/Controllers/FeatureFlagController.php`
- `app/Console/Commands/FeatureRolloutCommand.php`

## Why it is not just raw Pennant

Pennant provides the flag engine, but this project needs admin-oriented behavior on top of that:

- tenant-aware and role-aware rollout rules
- typed DTO and result boundaries
- API endpoints for admin pages
- realtime refresh signals when system-level settings change
- CI and regression coverage for menu / route behavior tied to flags

## Current API surface

The documented feature-flag endpoints live in:

- `docs/openapi.yaml`

Current operations include:

- list feature flags
- toggle global override
- purge override back to default behavior

## Operational usage

For command-line rollout operations, use:

```bash
php artisan feature:rollout
```

Composer shortcut:

```bash
composer run feature:rollout
```

This is the intended path when you want scripted or operational control without going through the admin UI.

## Frontend pairing

The intended paired frontend surface is:

- `obsidian-admin-vue/src/service/api/feature-flag.ts`
- `obsidian-admin-vue/src/views/feature-flag/index.vue`

That frontend now routes through the generated SDK rather than ad-hoc request calls.

## Design rule

Feature flags in this repository are meant for controlled rollout and operational safety.

They should not become a second hidden permission system.

Use them for:

- staged menu rollout
- system-level feature availability
- controlled rollout by scope
- temporary operational kill switches

Do not use them to replace durable RBAC or tenancy rules.
