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


## v1.0.2 — Repaired private loader-directory permissions (2026-10-13)

Observed v1.0.1 on aaPanel: archive hash PASS, nine offline tests PASS, ELF preflight PASS; the confined child failed before module inventory with `[Errno 13] Permission denied: '/php'`. Code review located a likely cause: library directories created under wrapper `umask 077` were mode `0700`, so UID 65534 could not traverse them to the ELF interpreter. Precise kernel-path root cause remains provisional until v1.0.2 is tested on host.

- Self-contained diagnostic artifact: `Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.2.zip`
- ZIP SHA-256: `52c79ce087743596d91d5f9e3ac8aa4ea9dfda7f4ea7e345e422fd4e9dd749b7`
- Byte size: `9472`
- Fresh-extraction SHA256SUMS: PASS; offline tests: 18/18 PASS; shell syntax: PASS; invalid probe approval phrase: rejected.
- Sandbox change: set mode `0755` on **ephemeral chroot library parent directories only** (the public chroot root was already `0755`); never chmod host parents or app source.
- Keep PHP binary and ELF loader `0755`, libraries `0644`; no-new-privileges, UID/GID 65534, tmpfs, mount/network/PID namespaces, chroot, pinned PHP hash, explicit approval phrase preserved.
- Success marker is printed only **after** successful ephemeral tmpfs unmount. `TEMPORARY_SANDBOX=REMOVED` additionally proves parent temporary directory cleanup.
- **aaPanel v1.0.2 live execution: NOT TESTED.** v1.0.1 superseded. No Barama bootstrap, DB, dynamic extensions, signed update, or rollback authorized.

### v1.0.2 aaPanel commands

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP=Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.2.zip
SHA=52c79ce087743596d91d5f9e3ac8aa4ea9dfda7f4ea7e345e422fd4e9dd749b7
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.2
bash run-on-aapanel.sh --check
# After preflight PASS only:
bash run-on-aapanel.sh --probe PROBE_INI_FREE_BUILTIN_MODULES_ONLY
```

Required to proceed: `BARAMA_PHP_INI_FREE_MODULE_PREFLIGHT=PASS`, `BARAMA_PHP_INI_FREE_MODULE_PROBE=PASS`, and `TEMPORARY_SANDBOX=REMOVED`. Otherwise stop, do not weaken confinement, and investigate result.
