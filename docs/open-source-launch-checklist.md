# Open Source Launch Checklist

Use this page when you want the shortest path to confirm that the backend and frontend are ready to be presented as a public open-source platform, not just as two repositories with green CI.

This checklist is intentionally cross-repository. It complements the backend-only release checklist in `docs/release-final-checklist.md`.

## 1. Public Entry Points

Confirm every external entry point is real and reachable.

- backend repository URL resolves and README matches current behavior
- backend docs site is publicly reachable after GitHub Pages deployment
- frontend repository URL resolves and README matches current behavior
- frontend docs site is publicly reachable after GitHub Pages deployment
- frontend preview URL loads the demo runtime without a blank screen

## 2. GitHub Settings

Confirm these repository settings are enabled and match the documented policy.

### Backend

- `main` branch protection is enabled
- required checks include backend quality, database test matrix, Octane smoke, Docker smoke, and image boot smoke
- Actions permissions are set to `Read and write`
- Packages permissions allow GHCR publish on release tags
- Pages source is set to `GitHub Actions`
- `CODEOWNERS` is active

### Frontend

- `main` branch protection is enabled
- required checks include frontend quality, contract gate, and full-stack pairing
- Actions permissions are set to `Read and write`
- Pages source is set to `GitHub Actions`
- `CODEOWNERS` is active

## 3. Artifact Truth

Confirm that every public claim has a matching release artifact.

### Backend artifacts

- GitHub Release note exists under `docs/releases/vX.Y.Z.md`
- GHCR image is published for the release tag
- GHCR image includes `linux/amd64` and `linux/arm64`
- CycloneDX SBOM exists for the release commit
- SBOM attestation exists
- published image scan ran for the release tag

### Frontend artifacts

- GitHub Release note exists under `docs/releases/vX.Y.Z.md`
- production app bundle is attached to the release
- demo preview bundle is attached to the release
- Pages bundle is attached to the release
- CycloneDX SBOM exists for the release commit
- SBOM attestation exists
- dist attestation exists

## 4. Cross-Repository Pairing

A public open-source launch is not complete unless the repositories still work together.

Confirm all of these are true.

- compatibility matrix is updated in both repositories
- frontend generated SDK is in sync with backend OpenAPI output
- frontend `pnpm typecheck:api` passes
- frontend `pnpm test:fullstack` passes against the seeded backend
- backend OpenAPI examples still reflect real response shapes for auth, user, role, and tenant flows

## 5. Runtime Confidence

Confirm the runtime story is coherent and documented.

- default runtime path is still `docker-compose.production.yml` with `nginx + php-fpm`
- explicit Octane path is still `docker-compose.octane.yml`
- health model is documented and still matches runtime behavior
- published image verification and runtime image scan are green
- docs do not imply that placeholder or preview-only capabilities are full production features

## 6. Support Readiness

Before promoting the project publicly, confirm the support surface is ready.

- `CONTRIBUTING.md` is current
- `SECURITY.md` is current
- `SUPPORT.md` is current
- issue and PR templates still match the actual repository workflow
- maintainer contact details are still valid

## 7. Launch Order

Use this order when announcing a new public version.

1. push backend `main`
2. wait for backend CI, supply-chain, and docs workflows to finish
3. push frontend `main`
4. wait for frontend quality, contract, full-stack pairing, preview, and docs workflows to finish
5. create annotated backend release tag
6. create annotated frontend release tag
7. confirm both releases publish the expected artifacts
8. publish announcement links only after docs, preview, and release artifacts are accessible

## 8. Hard Stop Conditions

Do not promote the stack publicly if any of these are true.

- backend docs site is still unavailable
- frontend preview is unavailable or blank
- full-stack pairing smoke is red
- compatibility matrix is outdated
- release notes and artifacts disagree on the version being promoted
- docs advertise capabilities that the preview or runtime cannot actually demonstrate
