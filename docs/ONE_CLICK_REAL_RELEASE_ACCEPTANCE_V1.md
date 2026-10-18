# Phase 4F — Real release acceptance (read-only gate)

Run only after verifying the publisher catalog SHA-256 against an **original independent off-server record**. A value obtained from the publisher host, this repository, or a CI fixture is not independent proof of authenticity.

The `StagingReleaseAcceptanceV1::inspect` class checks the existing staging-only readiness and signed ZIP inspector, compares the local public-key catalog with the supplied trusted SHA-256 and returns explicit blockers. It performs no network requests, installation, database access or writes. It NEVER declares a release installable, even with all read-only predicates true.

A real current-request publisher entitlement check and a separately approved isolated staging update/rollback remain mandatory. No endpoint or `Update Now` activation is added. A matching digest without independent provenance is not trustworthy.

CI tests cover missing and wrong catalog fingerprint, missing signed release, permanent entitlement and recovery blockers, and no-write behavior. Tests do not fabricate owner trust evidence.
