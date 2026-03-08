# Session and 2FA

This backend treats session handling and two-factor authentication as core platform behavior, not as optional UI polish.

That shows up in three places:

- token lifecycle rules
- user-visible session management
- TOTP policy and replay protection

## 1. Session model

The repository uses a dual-token session model:

- short-lived access token
- longer-lived refresh token

This is implemented on top of Sanctum, but the project does not stop at basic token issuance.

The backend also tracks session-oriented metadata such as:

- current session status
- device alias
- browser / OS / device classification
- remember-me behavior
- token counts and last-use timestamps

That gives the frontend a real session-management surface instead of a blind logout-only flow.

## 2. Session operations already supported

Current auth/session capabilities include:

- list current user sessions
- rename a session via device alias
- revoke a non-current session
- revoke the current session on logout
- refresh the current token pair

These flows are already exposed in the API and tested in feature coverage.

Representative areas:

- `app/Domains/Auth`
- `tests/Feature/AuthApiTest.php`

## 3. Why session management is treated as a first-class feature

In serious admin systems, "log out everywhere" is not enough.

Operators need to understand:

- which browser is active
- which session is current
- whether a refresh token is still present
- whether an alias was already assigned

This repository therefore treats session management as part of security posture, not just convenience.

## 4. Single-device policy

The project supports a configurable single-device login policy.

That matters because different deployments want different behavior:

- some internal tools require strict session replacement
- some teams want multi-device access with explicit revoke controls

The backend supports both without changing the frontend contract shape.

## 5. Two-factor authentication model

The 2FA path is TOTP-based and designed for admin-grade usage.

Supported flows include:

- generate setup secret
- enable 2FA
- disable 2FA
- require OTP on login when policy demands it

This repository also includes policy-level guidance around super-admin 2FA expectations through the security baseline tooling.

## 6. Replay protection

This backend intentionally goes beyond "valid TOTP within the time window".

TOTP verification includes replay protection, so a one-time code cannot be reused within the active validity window after a successful check.

That matters for:

- shoulder-surfing risk
- intercepted OTP reuse
- concurrent replay attempts during login or sensitive changes

Without replay protection, a TOTP implementation is easy to overrate.

## 7. Session + 2FA design intent

The security model here is not:

- issue token
- store token
- hope logout solves everything

Instead, it is:

- explicit session records
- explicit revoke/update flows
- explicit 2FA setup and enforcement
- explicit replay protection
- explicit security baseline review

That is the level expected from a serious Laravel admin backend baseline.

## 8. Related areas

- `docs/security-checklist.md`
- `docs/operations-hardening.md`
- `docs/testing.md`
- `docs/openapi-workflow.md`
