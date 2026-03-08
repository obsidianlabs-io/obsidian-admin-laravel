# Project Profiles

Project profiles give you a controlled way to bootstrap a deployment with a coherent operational posture instead of editing dozens of environment values by hand.

They are especially useful when you want different defaults for:

- internal admin systems
- stricter enterprise deployments
- lower-noise support or operations tooling

Profiles are defined in `config/project.php`.

## 1. What a profile can set

Profiles can influence:

- environment defaults written into `.env`
- menu and feature-flag posture
- authentication and security defaults
- theme defaults driven by environment values
- global audit policy overrides

This makes project setup reproducible instead of relying on tribal knowledge.

## 2. Safe preview mode

Preview a profile without writing `.env`:

```bash
php artisan project:profile:apply base
```

Use this when you want to inspect what the profile would do before changing runtime files.

## 3. Apply to the active environment

Write env overrides into the default `.env` and apply audit defaults:

```bash
php artisan project:profile:apply strict-enterprise --write-env
```

This is the normal "make the profile real" path.

## 4. Apply to a specific env file

Write to a custom environment file instead of `.env`:

```bash
php artisan project:profile:apply lean-support --write-env --env-file=.env.production
```

This is useful when you prepare deployment-specific env files ahead of rollout.

## 5. Skip audit changes when needed

If you only want env defaults and do not want to touch audit policy:

```bash
php artisan project:profile:apply base --write-env --no-audit
```

That is useful when audit policy is already managed separately in a running environment.

## 6. Included profiles

- `base`: balanced baseline for most internal admin systems
- `strict-enterprise`: stricter auth and compliance posture
- `lean-support`: lower-noise operations and support profile

## 7. What this command should and should not replace

Project profiles are a bootstrap and alignment tool.

They should help you:

- standardize initial deployment posture
- keep environments consistent across teams
- avoid ad-hoc env drift

They should not replace:

- tenant-specific runtime configuration
- release-time health checks
- environment secret management
- explicit production review

## 8. Recommended workflow

Recommended operator flow:

1. choose the closest built-in profile
2. preview the result without writing files
3. apply with `--write-env` only when the profile is correct
4. run migrations / health checks / release checklist afterwards

That keeps project profile usage aligned with deployment discipline instead of turning it into a hidden config mutation step.

## 9. Create your own profile

Add a new key under `config/project.php > profiles`:

- `description` (string)
- `env` (`KEY => value`)
- `audit_overrides` (`action => { enabled, samplingRate, retentionDays }`)

Then apply it:

```bash
php artisan project:profile:apply your-profile --write-env
```

## 10. Where to look next

- [Production Runtime](/production-runtime)
- [Runtime Topology](/runtime-topology)
- [Audit and Compliance](/audit-and-compliance)
- [Release Final Checklist](/release-final-checklist)
