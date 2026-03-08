# Realtime

## Current scope

Realtime support in this backend is intentionally focused.

This repository does not try to turn every write operation into a broadcast stream. The current design targets system-level updates that have clear operational value for admin clients.

## Runtime stack

The backend ships with:

- `laravel/reverb`
- event broadcasting support
- Octane / RoadRunner runtime option
- dockerized Reverb service in the production-like stacks

Relevant files:

- `composer.json`
- `docker-compose.production.yml`
- `docker-compose.octane.yml`

## Current broadcast event

The shared broadcast event used by the admin system is:

- `app/Domains/System/Events/SystemRealtimeUpdated.php`

It implements `ShouldBroadcastNow` and emits:

- event name: `system.realtime.updated`

This keeps system updates fast and avoids queue lag for these lightweight admin refresh signals.

## Current producers

Representative producers already wired in the backend:

- `app/Domains/System/Http/Controllers/FeatureFlagController.php`
- `app/Domains/System/Http/Controllers/AuditPolicyController.php`

That means feature-flag and audit-policy changes can notify connected admin clients without forcing manual refresh.

## Pairing rule

The intended frontend consumer is documented in:

- `obsidian-admin-vue/docs/realtime.md`

The frontend listens to a small set of system events and decides whether it can refresh safely.

## Design rule

Realtime here is not a vanity feature.

Use broadcast events when all of these are true:

- the remote update has cross-operator value
- the payload can stay small and stable
- the UI can reconcile or refresh safely
- the feature benefits from reduced polling or manual refresh

Do not broadcast everything by default. For ordinary CRUD, explicit refresh is often the better tradeoff.
