# Barama Auto Updater — INI-free PHP module sandbox

## Acceptance history (aaPanel; 2026-10-13)
- The earlier PHP 8.5.8 `php -n -v` confined probe passed.
- The module inventory **v1.0.0**: SHA-256 and read-only preflight passed; opt-in probe stopped at `STOP: tmpfs mounting failed`, before `php -n -m` executed. v1.0.0 is **superseded and must not be retried**.
- The underlying kernel errno was not captured, so the exact server-side cause is **not confirmed**.
- Suspected code defect: the Linux tmpfs mount call supplied `nosuid,nodev` as filesystem data rather than kernel mount flags.

## v1.0.1 diagnostic repair (non-production)
Artifact: `Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.1.zip`
ZIP SHA-256: `872a6cf8c875aa31d51ac89a16b5c02be8e8a52697bc3bd8d2e1d0ea0d1d2883`
- Pass `MS_NOSUID | MS_NODEV` as real mount flags and `size=220m,mode=0700` as tmpfs data.
- Explicit, typed `mount(2)`/`umount2(2)` signatures and fail-closed errno diagnostics.
- Require mount and network namespace isolation, private mount propagation, separate PID namespace, tmpfs chroot, `PR_SET_NO_NEW_PRIVS`, UID/GID 65534, and no PHP INI or external extensions.
- Pinned PHP `/www/server/php/85/bin/php`, SHA-256 `185d97290bc77e23ce77b0a655bea005ccbf8863285b62c8d2c9293105d7712c`.
- Offline checks: 9/9 tests passed; syntax, package checksum, fresh extraction, and invalid approval phrase checks passed.
- **aaPanel live execution of v1.0.1: NOT TESTED.**

## aaPanel instructions

Upload v1.0.1 ZIP to `/root`; run:

```bash
set -Eeuo pipefail
cd /root
ZIP=Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.1.zip
echo '872a6cf8c875aa31d51ac89a16b5c02be8e8a52697bc3bd8d2e1d0ea0d1d2883  Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.1.zip' | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.1
bash run-on-aapanel.sh --check
```

After `BARAMA_PHP_INI_FREE_MODULE_PREFLIGHT=PASS`, separately opt in:

```bash
cd /root/Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.1
bash run-on-aapanel.sh --probe PROBE_INI_FREE_BUILTIN_MODULES_ONLY
```

Success requires both `BARAMA_PHP_INI_FREE_MODULE_PROBE=PASS` and `TEMPORARY_SANDBOX=REMOVED`.

A failure such as `EPERM` must **not** be bypassed by weakening confinement. This kit does not copy application source, read or connect to database, modify production PHP configuration, activate IMAP, install updates, or touch signing identities. Do not merge as a production updater release.

## Release gate
CodeCanyon FINAL remains **ON HOLD**. Barama application bootstrap and genuine signed-update install and rollback are **NOT TESTED**.
