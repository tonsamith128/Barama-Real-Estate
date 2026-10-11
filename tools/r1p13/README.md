# R1p13 — Disposable Repository-Source HTTP and Auth Recovery QA

Status: draft CI implementation. **NOT an aaPanel deployment kit.**

## What it tests

This harness builds a test-only PHP 8.2 image from the *GitHub repository source*, starts a disposable MySQL 8.0 container, initializes a brand-new test database using the existing \`tools/browser-package-prepare.php\`, then starts a loopback-only PHP HTTP server.

The HTTP smoke test checks:
- Homepage and register/login page responses;
- Guest access rejection for the admin dashboard;
- Missing CSRF token rejection;
- Wrong password rejection;
- Test administrator login and authenticated dashboard response.

## Strict execution context

Only \`.github/workflows/r1p13-isolated-runtime.yml\` should invoke \`tools/r1p13/run-ci.sh\`.

Execution requires an ephemeral GitHub Actions runner matching GITHUB_WORKSPACE. Containers use an internal Docker bridge, with no published ports or host bind mounts. PHP source is copied from a fresh checkout. Application runtime storage and MySQL data are tmpfs backed. The application process uses no-new-privileges, a non-root UID, dropped capabilities, and a read-only image filesystem.

The runner creates fresh synthetic installation/licensing state only inside the disposable application container. Test credentials are generated specifically for the CI run. The MySQL container and temporary network are removed on exit; disposable volumes are removed too.

This is **not** a proof of zero side effects from arbitrary source code, and it is not a substitute for reviewing outbound integrations. Network isolation must be verified, and no production endpoints or production credentials may be introduced.

## Important scope distinction

The source in the GitHub repository currently reports VERSION 0.9.22-dev. The live production website and its pinned backup are not assumed to be the same revision. The CI harness neither loads nor executes the pinned production backup (20261011T064358Z-25403005).

Even if this workflow passes:
- R1p13 archive-source parity and full restored-production-application runtime recovery are **NOT** complete.
- Live PHP-FPM configuration is **NOT** executed by this test (PHP CLI built-in server only).
- No real customers or production database are accessed.
- Turnstile is **NOT** enabled on forms yet.
- The secure updater **DOES NOT** download or install updates yet.

## Next acceptance requirements

1. Map the backed-up live source to a source commit or equivalent immutable fileset.
2. Develop a separate sandbox configuration replacement strategy for the exact restored archive, including safe external service blocking.
3. Reconstruct the pinned 62-table restored database inside a disposable environment and verify route/login behavior using only synthetic test accounts.
4. Test PHP-FPM and HTTP parity using a full sandbox, and include realistic rollback and recovery drills.
5. Keep PR draft and do not deploy until all of these pass.
