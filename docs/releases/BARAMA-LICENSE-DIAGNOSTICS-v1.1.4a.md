# Barama License Diagnostics v1.1.4a

The previous diagnostic returned no PHP result on aaPanel because its standalone process did not satisfy the installation-state initialization contract. That was a diagnostic-tool issue, not a publisher license verdict.

## Verified corrected release

- ZIP: `Barama-License-Publisher-ReadOnly-Diagnostics-v1.1.4a.zip`
- SHA-256: `7fa5599dc81153f9ef712f3ba6ba0458f26b404399eb74915b772a8751e71ac3`
- QA: **25/25 PASS** in package directory and 25/25 PASS after clean ZIP extraction
- Internal checksums: PASS
- PHP syntax lint: PASS
- All files packaged, no website application files replaced

## Changes

- Correct state-reader initialization before loading protected Barama installation records.
- Detect silent PHP child termination with a distinct fail-closed status.
- New tests reproduce protected PHP state loading and check the new status handling.
- Offline preflight and publisher validation remain separate, explicit actions. One publisher validation request is made only during `--diagnose` if local preconditions pass.

## Production acceptance

Not yet run against the user's aaPanel host. Reverify ZIP SHA, internal manifest and local tests before preflight; use the diagnostic result classification to determine the next repair. Do not alter the stored license, domain binding or publisher status on the basis of the earlier silent diagnostic.

The executable scripts remain bundled in the conversation ZIP, not merged to the product repository. This page is a release record only.
