# Barama Real Estate — 0.9.22-dev acceptance candidate

Source commit: `9ad9f6405f3206d7d5f411158a338fec24cbbcde`
Source tree: `ca5c3603b0153b66c3bb831f782cd8dbee562b8c`
Customer ZIP: `Barama-Real-Estate-0.9.22-dev.zip`
SHA-256: `d7a7898465edb0f897325d47123eaf61eac521e941ebf6c54875ac7167db8eff`
Size: 1203338 bytes
Selected customer files: 160

## Packaging provenance

GitHub Actions run 35945995946 was not assigned a runner and executed zero Foundation QA steps. To avoid representing that infrastructure failure as an application failure, this acceptance candidate was assembled through the connected GitHub repository API from the exact Git blobs at the source commit above.

Every selected customer file was fetched by Git blob SHA and its UTF-8 byte length was checked against the recursive Git tree metadata before the archive was sealed. The selection follows the customer-package inclusion/exclusion rules in `tools/build-package.php`, including the protected runtime empty directories and `tools/notifications.php`.

The ZIP uses the standard ZIP STORE method rather than relying on a GitHub runner's libzip compression choice. Its archive comment is `Barama Real Estate 0.9.22-dev customer package.`.

This branch is an acceptance handoff only. It does not modify `main`, does not close any RC1 gate, and does not replace the required server-side exact-package checks. Before deployment, verify the SHA-256 sidecar and run the repository package compliance/installer/upgrade checks against this exact ZIP.
