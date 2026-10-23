# Production One-Click Updater acceptance — 2026-10-23

## Verified production result

- Website: `baramarealestate.tonsamith.com`
- Upgraded through the administrator-facing signed updater: **0.9.23 → 0.9.24**
- Publisher release: `barama-0.9.24-production-oneclick`
- Published ZIP SHA-256: `47905b303c3fce7e93591ebf0f5a5f8ad2f6e46bcc98d7bcaa712e50e3a26e8f`
- Installed v0.9.24 updater JavaScript SHA-256: `8385ba6f626a966d53e067089ce101195c401f6dc006ea0a03e8abdf1e48c7f5`
- Root-private receipt: `completed_file_only` for v0.9.24, with matching published ZIP digest
- No active or pending portable job: `current.json` and `active.json` absent
- Vault permission: `0700`; production homepage: HTTP 200
- One-shot worker status inspected: `Result=success`, `ExecMainStatus=0`, inactive between runs

The administrator screenshot accepted the **Verified previous transaction → Update installed successfully** card above a separate **Next update discovery** panel. This historical completion must not be confused with a freshly available release.

## Changes carried into Main

Main was already at **v0.9.25** when the v0.9.24 production screenshot was approved. Do **not** replace newer Main source with the version-pinned live view or reuse the signed v0.9.24 artifacts.

Instead, the separate completion component is version-neutral and:

- reads the already-installed application's private receipt, never user-supplied version or hash;
- checks there is no active/current portable job and that receipt version matches the installed VERSION;
- renders previous completion only when the underlying receipt is `completed_file_only`;
- keeps next-update discovery separate and changes only an exact stale download-only notice;
- fails closed when the receipt is missing, unreadable, or inconsistent;
- never installs, publishes, requests, or stages software;
- claims **file-only completion**, not independently demonstrated database backup restoration.

## Separate host-worker/passcode integration

The live v0.9.24 Production host button originally returned HTTP 428 due to `ChangeConfirmationMiddleware` requiring a 6-digit passcode grant. The scoped Production bridge fixed this without disabling the middleware; the single-use grant is required per protected request. The v0.9.24 Production-specific bridge and worker routes have **not** been copied over the structurally newer v0.9.25 Main implementation in this completion-display change. Test and reconcile those functional components separately before packaging a later release.

## Validation contract for later releases

For any `VERSION`, no previously completed card may be displayed if the private receipt is absent, a job is pending, its target version differs, the receipt is malformed, or the configured source vault is unsafe. Completed receipt display is historical evidence, not permission to start another job.

Rollback behavior was **not independently exercised** in the Production v0.9.24 acceptance run. Avoid presenting rollback as verified on that basis.
