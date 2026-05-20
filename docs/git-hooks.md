# Git Hooks

Local Git hooks for Obsidian Admin Laravel, powered by [Lefthook](https://github.com/evilmartians/lefthook).

## Overview

The project uses a three-stage hook toolchain that catches common quality issues **before** code reaches CI:

| Stage | Trigger | Purpose | Target Time |
|-------|---------|---------|-------------|
| **Pre-Commit** | `git commit` | Fast, file-scoped lint & format checks on staged files | < 10 seconds |
| **Commit-Msg** | `git commit` (after message entry) | Conventional Commit format validation | < 1 second |
| **Pre-Push** | `git push` | Comprehensive quality gates (PHPStan, architecture, contracts) | < 5 minutes |

### Relationship to CI

Hooks **complement but do NOT replace** CI. CI remains the source of truth for merge eligibility.

```
Developer Workstation                          Remote (GitHub)
─────────────────────                          ───────────────
git commit
  → Pre-Commit (fast subset on staged files)
  → Commit-Msg (format check)

git push
  → Pre-Push (heavy subset, full repo)
                                               → quality.yml (Pint, Larastan, Pest, Deptrac, path safety)
                                               → ci.yml (full test suite)
                                               → supply-chain.yml (dependency audit)
```

Hooks run a **subset** of what CI runs. If hooks pass, CI will almost always pass too. But CI runs additional checks (full test suite, frontend contract typecheck, dependency audit) that are impractical locally.

---

## Installation

### Automatic (recommended)

Hooks are installed automatically when you run:

```bash
composer install
```

The `post-install-cmd` and `post-update-cmd` Composer events trigger `scripts/hooks/install-lefthook.php`, which detects your environment and installs hooks if Lefthook is available.

### Manual

```bash
composer run hooks:install
```

### Uninstall

```bash
composer run hooks:uninstall
```

### Installing Lefthook

If the Lefthook binary is not on your PATH, the installer prints guidance and exits cleanly (Composer install still succeeds). Install Lefthook with:

| Platform | Command |
|----------|---------|
| macOS | `brew install lefthook` |
| Linux (snap) | `sudo snap install lefthook --classic` |
| Linux (curl) | `curl -fsSL https://get.lefthook.com \| sh` |
| Windows | `scoop install lefthook` |
| Any OS (Go) | `go install github.com/evilmartians/lefthook@latest` |

Then re-run `composer run hooks:install`.

---

## Hook Stages and Checks

All configuration lives in a single file: **`lefthook.yml`** at the repository root.

### Pre-Commit Stage

Runs in **parallel** on staged files only. Completes in under 10 seconds for typical commits.

| Check | Glob | Command | Auto-fix? | Quality Script |
|-------|------|---------|-----------|----------------|
| `php-lint` | `*.php` | `php -l {staged_files}` | No | — |
| `pint-fix` | `*.php` | `./vendor/bin/pint {staged_files}` | Yes (re-stages) | `composer run format` |
| `json-syntax` | `*.json` | PHP inline JSON validator | No | — |
| `yaml-syntax` | `*.{yaml,yml}` | PHP inline YAML validator | No | — |
| `secret-scan` | all staged | `gitleaks protect --staged --no-banner` | No | — |
| `large-file-check` | all staged | `scripts/hooks/check-file-size.sh {staged_files}` | No | — |

**Key behaviors:**

- `stage_fixed: true` on `php-lint` and `pint-fix` means Pint auto-fixes are re-staged automatically — you don't need to `git add` again.
- `secret-scan` degrades gracefully: if `gitleaks` is not installed, it prints a warning and continues.
- `large-file-check` blocks any staged file exceeding 5 MB.

### Commit-Msg Stage

| Check | Command | Quality Script |
|-------|---------|----------------|
| `conventional-commit` | `scripts/hooks/validate-commit-msg.sh {1}` | — |

Validates the commit message against [Conventional Commits 1.0.0](https://www.conventionalcommits.org/):

```
<type>(<scope>)?: <description>
```

**Allowed types:** `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`, `revert`

**Rules:**
- Subject line must be ≤ 72 characters
- Merge commits (`Merge branch ...`, `Merge pull request ...`) are always accepted
- Breaking changes use `!` suffix: `feat(auth)!: remove legacy login`

### Pre-Push Stage

Runs the heavy quality gates before code reaches the remote.

| Check | Command | Quality Script |
|-------|---------|----------------|
| `branch-guard` | `scripts/hooks/branch-guard.sh` | — |
| `analyse` | `composer run analyse` | PHPStan/Larastan |
| `architecture` | `composer run quality:architecture` | Pest architecture + Deptrac |
| `path-safety` | `composer run docs:path-safety` | Public path safety check |
| `openapi-lint` | `composer run openapi:lint` | OpenAPI spec validation |
| `contract-check` | `composer run contract:check` | API contract snapshot |
| `security-check` | `composer run security:check` | Security baseline audit |

**Branch guard:** Direct pushes to `main` and `release/*` are blocked. Push to a feature branch and open a pull request instead.

---

## Bypass Mechanisms

For emergencies or WIP workflows, hooks can be bypassed:

| Mechanism | Scope | Example |
|-----------|-------|---------|
| `--no-verify` | Skips pre-commit + commit-msg | `git commit --no-verify -m "wip"` |
| `--no-verify` on push | Skips pre-push | `git push --no-verify` |
| `LEFTHOOK_EXCLUDE` | Skips named stage(s) | `LEFTHOOK_EXCLUDE=pre-push git push` |
| `LEFTHOOK_EXCLUDE` (multiple) | Skips multiple stages | `LEFTHOOK_EXCLUDE=pre-commit,commit-msg git commit -m "wip"` |

> **Warning:** Bypassed commits will still be checked by CI. Use bypass only for genuine emergencies or local WIP commits you plan to squash.

---

## How to Add a New Check

Adding a new check takes one edit to `lefthook.yml` and optionally one new `composer.json` script.

### Worked Example: Adding a Blade Template Linter

**Step 1:** Add a composer script (if the tool isn't already wired up):

```json
// composer.json → scripts
"blade:lint": "php artisan blade:lint"
```

**Step 2:** Add the check to the appropriate stage in `lefthook.yml`:

```yaml
# For a fast, file-scoped check → pre-commit
pre-commit:
  parallel: true
  commands:
    # ... existing commands ...
    blade-lint:
      glob: "*.blade.php"
      run: "composer run blade:lint -- {staged_files}"
```

Or for a repo-wide check that should run before push:

```yaml
# For a heavy, repo-wide check → pre-push
pre-push:
  commands:
    # ... existing commands ...
    blade-lint:
      run: "composer run blade:lint"
```

**Step 3:** Commit both files:

```bash
git add lefthook.yml composer.json
git commit -m "build(hooks): add Blade template linting to pre-commit"
```

That's it. All contributors get the new check on their next `git pull` + `composer install`.

### Guidelines for Choosing a Stage

| Criteria | Stage |
|----------|-------|
| Fast (< 2s), file-scoped, can filter by glob | Pre-Commit |
| Validates commit metadata | Commit-Msg |
| Slow (> 10s), needs full repo context | Pre-Push |

---

## Troubleshooting

### Missing Lefthook Binary

**Symptom:** After `composer install`, you see:

```
⚠️  Lefthook is not installed. Git hooks will NOT be active.
```

**Fix:** Install Lefthook using one of the methods in the [Installation](#installing-lefthook) section, then run:

```bash
composer run hooks:install
```

### Slow First Run (Cold Caches)

**Symptom:** Pre-push checks take much longer than expected on the first run.

**Cause:** PHPStan, Larastan, and Deptrac build analysis caches on first execution. Subsequent runs use the warm cache and are significantly faster.

**Fix:** This is expected behavior. The first push after a fresh clone or cache clear will be slow (up to 5 minutes). Subsequent pushes typically complete in 1–2 minutes. You can pre-warm caches:

```bash
composer run analyse        # Warms PHPStan cache
composer run quality:architecture  # Warms Deptrac cache
```

### Accidental Bypass

**Symptom:** You committed with `--no-verify` by habit or muscle memory, and now CI is failing.

**Fix:** Amend the commit and let hooks run:

```bash
# Fix the issue, then:
git add .
git commit --amend --no-edit
# Hooks will run on the amended commit
```

Or if already pushed, fix forward:

```bash
# Fix the issue
git add .
git commit -m "fix: resolve style/lint issues"
git push
```

**Prevention:** If you find yourself bypassing frequently, check if a specific check is misconfigured or too slow and open an issue.

### False Positives in Secret Scanner

**Symptom:** Gitleaks flags a string that isn't actually a secret (e.g., a test fixture, a documentation example, or a hash constant).

**Fix:** Create a `.gitleaks.toml` file at the repository root with an allowlist:

```toml
[allowlist]
  description = "Project-specific allowlist"
  paths = [
    '''tests/fixtures/.*''',
    '''docs/.*'''
  ]
  regexes = [
    '''EXAMPLE_[A-Z_]+_KEY'''
  ]
```

Alternatively, add an inline comment to suppress a specific line (check [gitleaks documentation](https://github.com/gitleaks/gitleaks#configuration) for the latest syntax).

If the false positive is in a test fixture, consider whether the fixture truly needs a realistic-looking secret or if a placeholder would suffice.

### Missing Gitleaks

**Symptom:** You see this warning during pre-commit:

```
⚠️  gitleaks not installed — secret scan skipped
```

**Impact:** Low risk for most commits. The secret scan is a defense-in-depth measure; CI also runs security checks.

**Fix (optional):** Install gitleaks:

```bash
# macOS
brew install gitleaks

# Linux
sudo apt install gitleaks
# or: go install github.com/gitleaks/gitleaks/v8@latest

# Windows
scoop install gitleaks
```

The secret scan will activate automatically on your next commit — no reconfiguration needed.

---

## Example Transcripts

### Successful Pre-Commit Run

```
$ git add app/Models/User.php config/auth.php
$ git commit -m "feat(auth): add remember-me token rotation"

╭──────────────────────────────────────╮
│ 🥊 lefthook  pre-commit             │
╰──────────────────────────────────────╯
┃  php-lint ✔️
┃  pint-fix ✔️  (1 file auto-fixed and re-staged)
┃  secret-scan ✔️
┃  large-file-check ✔️
┃  json-syntax (skip) no matching files
┃  yaml-syntax (skip) no matching files

✅ All checks passed

[feature/auth-token 3a1b2c3] feat(auth): add remember-me token rotation
 2 files changed, 45 insertions(+), 12 deletions(-)
```

### Failing Pre-Commit Run

```
$ git add app/Services/PaymentService.php
$ git commit -m "feat(payments): integrate stripe webhook"

╭──────────────────────────────────────╮
│ 🥊 lefthook  pre-commit             │
╰──────────────────────────────────────╯
┃  php-lint ✔️
┃  pint-fix ✔️
┃  secret-scan ❌
│
│  🔑 Potential secret detected in staged files. Remove the secret before committing.
│
│  Finding:     app/Services/PaymentService.php:23
│  Rule:        stripe-api-key
│  Match:       <REDACTED_STRIPE_KEY>
│
┃  large-file-check ✔️
┃  json-syntax (skip) no matching files
┃  yaml-syntax (skip) no matching files

❌ pre-commit failed (secret-scan)

$ # Fix: move the key to .env and reference via config()
$ vim app/Services/PaymentService.php
$ git add app/Services/PaymentService.php
$ git commit -m "feat(payments): integrate stripe webhook"
# ✅ Passes on retry
```

### Failing Commit-Msg

```
$ git commit -m "updated the auth stuff"

╭──────────────────────────────────────╮
│ 🥊 lefthook  commit-msg             │
╰──────────────────────────────────────╯

❌ Commit message does not follow Conventional Commits format.

Expected: <type>(<scope>)?: <description>
Example:  feat(auth): add two-factor authentication

Allowed types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert

$ git commit -m "fix(auth): update token refresh logic"
# ✅ Passes
```

### Failing Pre-Push (Branch Guard)

```
$ git push origin main

╭──────────────────────────────────────╮
│ 🥊 lefthook  pre-push               │
╰──────────────────────────────────────╯

❌ Direct push to protected branch 'main' is blocked.

Please push to a feature branch and open a pull request instead.
  git push origin HEAD:refs/heads/feature/your-branch-name

To bypass (emergency only): LEFTHOOK_EXCLUDE=pre-push git push

$ git push origin HEAD:feature/my-fix
# ✅ Pre-push checks run against feature branch
```

---

## Configuration Reference

The full configuration lives in `lefthook.yml` at the repository root. Key settings:

```yaml
assert_lefthook_installed: true   # Fail if lefthook binary is missing

pre-commit:
  parallel: true                  # Run all pre-commit checks concurrently
  commands:
    <name>:
      glob: "<pattern>"           # Only run on matching staged files
      run: "<command>"            # Shell command to execute
      stage_fixed: true           # Re-stage files after auto-fix

commit-msg:
  commands:
    <name>:
      run: "<command> {1}"        # {1} = path to commit message file

pre-push:
  commands:
    <name>:
      run: "<command>"            # Full repo-scope command
```

For the complete Lefthook configuration reference, see the [Lefthook documentation](https://github.com/evilmartians/lefthook/blob/master/docs/configuration.md).

---

## Files

| Path | Purpose |
|------|---------|
| `lefthook.yml` | Hook configuration (repo root) |
| `scripts/hooks/install-lefthook.php` | Composer-triggered installer with CI/container detection |
| `scripts/hooks/validate-commit-msg.sh` | Conventional Commit message validator |
| `scripts/hooks/branch-guard.sh` | Protected branch push guard |
| `scripts/hooks/check-file-size.sh` | Large file blocker (> 5 MB) |
| `docs/git-hooks.md` | This documentation |
