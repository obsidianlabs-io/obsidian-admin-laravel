# Project Profiles

Project profiles let you bootstrap a new deployment with consistent settings for:

- menu feature flags
- authentication/security defaults
- theme defaults (via env)
- global audit policy defaults

Profiles are defined in `config/project.php`.

## Apply Profile

Preview without writing `.env`:

```bash
php artisan project:profile:apply base
```

Write env overrides into `.env` and apply audit defaults:

```bash
php artisan project:profile:apply strict-enterprise --write-env
```

Write to a custom env file:

```bash
php artisan project:profile:apply lean-support --write-env --env-file=.env.production
```

Skip audit policy changes:

```bash
php artisan project:profile:apply base --write-env --no-audit
```

## Included Profiles

- `base`: balanced baseline for most internal admin systems.
- `strict-enterprise`: stricter auth/compliance defaults.
- `lean-support`: lower-noise operations/support profile.

## Create Your Own Profile

Add a new key under `config/project.php > profiles`:

- `description` (string)
- `env` (`KEY => value`)
- `audit_overrides` (`action => { enabled, samplingRate, retentionDays }`)

Then apply:

```bash
php artisan project:profile:apply your-profile --write-env
```
