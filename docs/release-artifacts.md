# Release Artifacts

This page is the single entrypoint for the backend release outputs that matter to downstream consumers.

Use it when you need to answer:

- what gets published on a backend release tag
- which artifacts are machine-consumable
- which artifacts are only for audit and supply-chain verification
- how to verify a release after a tag push

## 1. GitHub Release

Every `v*` tag triggers the release workflow:

- a GitHub Release is created
- the body prefers `docs/releases/vX.Y.Z.md`
- if that file is missing, the workflow falls back to `CHANGELOG.md`

Recommended source of truth:

- curated release note: `docs/releases/vX.Y.Z.md`
- change log history: `CHANGELOG.md`

## 2. GHCR Runtime Image

Stable tags publish a multi-arch runtime image to:

- `ghcr.io/obsidianlabs-io/obsidian-admin-laravel:<full-version>`

Published tag forms:

- `1.2.0`
- `1.2`
- `1`
- `latest` for stable non-prerelease tags only

Published platforms:

- `linux/amd64`
- `linux/arm64`

The release workflow also performs a post-publish self-check:

- wait until the just-published tag is available from GHCR
- pull the published tag
- cold boot the container
- run `php artisan about --only=environment`

That self-check proves:

- the registry push succeeded
- the tag is consumable from GHCR
- the final runtime image can bootstrap Laravel

It does **not** replace route-level health verification. HTTP probes still belong to a compose or edge-backed runtime path.

## 3. SBOM

The backend supply-chain workflow publishes a CycloneDX SBOM artifact:

- artifact name: `backend-sbom-cyclonedx`

This SBOM is runtime-oriented and is meant for:

- dependency review
- downstream compliance checks
- release inventory traceability

The generated local path is ignored by git:

- `build/backend-sbom.cyclonedx.json`

## 4. Attestations

The repository publishes attestations for:

- source bundle provenance
- SBOM artifact provenance
- GHCR image provenance

Use these when you need to prove that:

- a release artifact came from this repository
- the published image matches the workflow output
- the SBOM belongs to the exact release pipeline that produced it

## 5. Release Image Vulnerability Scan

Every stable backend tag now scans the just-published GHCR runtime image.

The release workflow:

- pulls the versioned release image from GHCR
- generates a Trivy SARIF report
- uploads the report as the `backend-release-image-scan` artifact
- fails the release workflow if a **critical** vulnerability is detected in the published image

This answers a different question than SBOM and attestation:

- SBOM shows **what is inside**
- attestation shows **where it came from**
- release image scanning shows **whether the published runtime currently contains known critical vulnerabilities**

## 5.5. Continuous Runtime Image Scans

Release-time image scanning is not the only image security gate.

`Backend Supply Chain` also builds the repository runtime image and scans it on:

- pull requests
- pushes to `main`
- nightly schedule
- manual workflow dispatch

Published artifact:

- `backend-runtime-image-scan`

Use this artifact when you want to answer:

- "Would this branch introduce a critical image vulnerability before a tag exists?"
- "Did the current `main` runtime drift into a vulnerable state overnight?"

## 6. Recommended Consumer Paths

Choose the artifact based on your goal.

### Runtime consumption

Use:

- GHCR image
- `docs/production-runtime.md`

Best for:

- container deployment
- internal platform rollout
- immutable release promotion

### Release review

Use:

- GitHub Release
- `docs/releases/vX.Y.Z.md`
- `CHANGELOG.md`

Best for:

- operator review
- human-readable release summaries
- change communication

### Compliance and provenance

Use:

- CycloneDX SBOM artifact
- SBOM attestation
- image attestation
- runtime image scan artifact
- release image vulnerability scan artifact

Best for:

- supply-chain verification
- regulated environments
- internal audit trails

## 7. Post-Tag Verification

After pushing a release tag, verify:

1. the GitHub Release exists
2. the GHCR image has the expected version tag
3. the GHCR manifest includes `linux/amd64` and `linux/arm64`
4. the `backend-sbom-cyclonedx` artifact exists
5. the SBOM attestation exists
6. the `backend-runtime-image-scan` artifact exists for the release commit
7. the `backend-release-image-scan` artifact exists
8. the release image post-publish verification job is green
9. the published image vulnerability scan job is green

If you need route-level HTTP verification, use the compose-backed runtime from `docs/production-runtime.md` and call:

```bash
curl http://127.0.0.1:8080/api/health/live
```

## 8. Related Documents

- `docs/production-runtime.md`
- `docs/release-sop.md`
- `docs/release-final-checklist.md`
- `docs/github/repository-setup-checklist.md`
