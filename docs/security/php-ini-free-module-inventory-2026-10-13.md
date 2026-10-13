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


## 2026-10-13: v1.0.2 acceptance — confirmed on aaPanel

- ZIP SHA-256 verified; 18/18 offline tests PASS; PHP binary integrity PASS.
- `ELF_LIBRARY_RESOLUTIONS=48`; `IMAP_AND_DYNAMIC_EXTENSIONS=EXCLUDED`.
- `EPHEMERAL_MOUNT_AND_NETWORK_NAMESPACE=PASS`; `NONROOT_EXECUTION=65534:65534`.
- `PHP_INI_CONFIGURATION=DISABLED`; Barama source and database socket not copied.
- `PHP_MODULE_COUNT=46` with built-in `PDO`, `pdo_mysql`, `openssl`, `json`, `SPL`, `mbstring`, `intl`, `mysqli`, `mysqlnd`, and others.
- `BARAMA_PHP_INI_FREE_MODULE_PROBE=PASS`; `TEMPORARY_SANDBOX=REMOVED`.
- `fileinfo` and `zip` were **not listed** among INI-free built-ins. They were previously inventoried as available .so candidates; availability does not establish compatibility.
- This is PHP module discovery, **not** application bootstrap or update/rollback acceptance.

## Next isolated acceptance: fileinfo.so and zip.so — v1.0.3

Package: `Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.3.zip`
ZIP SHA-256: `4ad57c50dcc7b6fe9ecae6583f366f671c756c7dc8c4d74d166c146dae178f15`
ZIP size: 16246 bytes

This diagnostic kit carries the accepted v1.0.2 core probe source and extension-specific offline tests. It found no need to alter production PHP configuration.

Before executing extensions, the read-only preflight:
- Re-verifies the pinned PHP binary SHA-256 `185d97290bc77e23ce77b0a655bea005ccbf8863285b62c8d2c9293105d7712c` and all core library paths.
- Locates exactly one `fileinfo.so` and one `zip.so` under `/www/server/php/85/lib/php/extensions/no-debug-non-zts-*/`, checks root ownership and non-writable parents.
- Inspects ELF dependency resolutions; disallows unexpected search paths (RPATH/RUNPATH), TEXTREL, unresolved libraries, and unsafe paths.

After a separately authorized opt-in, the code reuses the v1.0.2 **fresh, private 220-MiB tmpfs, chroot, mount/network/PID namespaces, no-new-privileges, UID/GID 65534**. It copies only the two candidate PHP extensions and verified root-owned ELF libraries, then compares `php -n -m` with isolated `php -n -d extension=/extensions/fileinfo.so -m` and zip-only equivalent. The module delta must be exactly the selected module, with no warnings or extra modules. Neither extension is activated in the host PHP installation; IMAP is excluded.

Local quality gate: 41/41 offline unit tests PASS, fresh-extraction internal hashes PASS, shell parse PASS, explicit approval check PASS. **aaPanel live extension probe is NOT YET TESTED.**

### aaPanel commands
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP=Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.3.zip
SHA=4ad57c50dcc7b6fe9ecae6583f366f671c756c7dc8c4d74d166c146dae178f15
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.3
bash run-on-aapanel.sh --check
# ONLY IF the above explicitly passes:
bash run-on-aapanel.sh --probe PROBE_ISOLATED_FILEINFO_ZIP_MODULES_ONLY
```

Successful runtime requires all four markers:
```ini
EXTENSION_FILEINFO_ISOLATED_LOAD=PASS
EXTENSION_ZIP_ISOLATED_LOAD=PASS
BARAMA_PHP_SELECTED_EXTENSION_SANDBOX_PROBE=PASS
TEMPORARY_SANDBOX=REMOVED
```

Failure is NOT a reason to weaken network/namespace/chroot/sandbox restrictions. No customer data, app execution, database authentication, production PHP config change, signing identity, or updater action is authorized.

CodeCanyon FINAL stays **ON HOLD** until genuine signed release installation and rollback are tested.


## 2026-10-13: v1.0.3 preflight failure; v1.0.4 correction

### Observed aaPanel v1.0.3 result
- ZIP integrity verified and original 41/41 offline unit tests passed.
- `bash run-on-aapanel.sh --check` then raised `NameError: name 're' is not defined` in `extension_probe.py`, `extensions()`, at the `re.fullmatch(...)` extension ABI-directory check.
- **Read-only preflight: FAIL**; isolated PHP dynamic extension execution **NOT STARTED**; neither `fileinfo.so` nor `zip.so` was loaded.
- **v1.0.3 superseded: do not rerun.** Prior built-in PHP core sandbox v1.0.2 acceptance remains unaffected.

### Repaired v1.0.4 diagnostic-only package

Archive: `Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.4.zip`

ZIP SHA-256: `ca7423f70950d2a4e137eededb6c8bbfc894dac3be70aae4ded7426168e77304`

Size: 17940 bytes.

Changes:
- Explicit `import re` in `extension_probe.py`. No weakening of the root-owned path, ELF, namespace, non-root, chroot, or approved-extension policies.
- Added 7 tests which **execute the actual extension-discovery and CLI `--check` code paths** with synthetic fixtures, so missing imported modules are caught rather than merely checking a regex in the test.
- **48/48 offline tests PASS**; source syntax and shell parse PASS; SHA256SUMS verified after fresh extraction; invalid opt-in phrase rejected.
- These checks were conducted in the package build container using mock extension files. No actual aaPanel PHP extension loader was run locally.

To use aaPanel (upload ZIP to `/root` first):

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.4.zip"
SHA="ca7423f70950d2a4e137eededb6c8bbfc894dac3be70aae4ded7426168e77304"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.4
bash run-on-aapanel.sh --check
```

Run opt-in only after `BARAMA_PHP_SELECTED_EXTENSION_PREFLIGHT=PASS`:

```bash
cd /root/Barama-PHP-INI-Free-Extension-Probe-aaPanel-v1.0.4
bash run-on-aapanel.sh --probe PROBE_ISOLATED_FILEINFO_ZIP_MODULES_ONLY
```

Accept only if `EXTENSION_FILEINFO_ISOLATED_LOAD=PASS`, `EXTENSION_ZIP_ISOLATED_LOAD=PASS`, `BARAMA_PHP_SELECTED_EXTENSION_SANDBOX_PROBE=PASS`, and `TEMPORARY_SANDBOX=REMOVED`.

**v1.0.4 aaPanel live result: NOT YET TESTED.** Do not change PHP INI, copy Barama source, touch MySQL, modify firewall, sign a release, or publish. CodeCanyon FINAL remains **ON HOLD**.

## 2026-10-13: v1.0.4 aaPanel acceptance — PASS

User-provided aaPanel transcript:
- ZIP verified against `ca7423f70950d2a4e137eededb6c8bbfc894dac3be70aae4ded7426168e77304`.
- 48/48 offline tests PASS; `PHP_RUNTIME_BINARY_PIN=PASS`, `PHP_CORE_ELF_RESOLUTIONS=48`, `ADDITIONAL_ELF_RESOLUTIONS=2`.
- `EXTENSION_FILEINFO_SHA256=4b4bfc5676233036cf4856c9ddc01c5274a1b5a6d004e313655a7dddfaef4360`.
- `EXTENSION_ZIP_SHA256=6a93dee35e7d42fd18bae63dfb48088404b3647d37080e06f9e22e7631382968`.
- `EXTENSION_FILEINFO_ISOLATED_LOAD=PASS`, `EXTENSION_ZIP_ISOLATED_LOAD=PASS`.
- `BARAMA_PHP_SELECTED_EXTENSION_SANDBOX_PROBE=PASS`, `TEMPORARY_SANDBOX=REMOVED`.
- PHP INI disabled, mount/network/PID namespaces isolated, UID/GID 65534, IMAP excluded, app code and database sockets not copied.
- **Acceptance scope: two modules loaded separately**, without actual fileinfo MIME or ZipArchive functional exercise, app bootstrap or updater execution.

## v1.0.5 combined fileinfo + zip functional smoke (proposed next gate)

Package: `Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.5.zip`
SHA-256: `913928416f54cc036df5df58ff1894093420ed4c4792a1d8844cfed7554d4808`
Size: 20,657 bytes.
- Reuses previously accepted v1.0.4 isolated PHP loader/confinement, pins PHP SHA and BOTH extension SHAs captured above.
- Revalidates baseline and individually activated extensions (unchanged module deltas), then loads `fileinfo` and `zip` **together** with `php -n -d extension=... -d extension=... -r` as UID/GID 65534 in private tmpfs chroot.
- Synthetic-only functional assertions: `finfo(FILEINFO_MIME_TYPE)->buffer` returns `text/plain`; `ZipArchive` creates, reads, verifies and unlinks a small synthetic zip in `/scratch` inside private tmpfs; scratch directory must be empty.
- No Barama source/config/DB/socket, no IMAP, no normal PHP INI, no license/signer, no firewall/service changes. A failure must fail closed; do not relax confinement or skip checks to obtain PASS.
- Offline 61/61 tests PASS; fresh ZIP SHA256SUMS PASS; shell lint PASS; synthetic PHP syntax lint PASS; invalid approval phrase rejected.
- **v1.0.5 live aaPanel execution: NOT YET TESTED.** This is NOT a Barama bootstrap test.

### aaPanel instructions for v1.0.5
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.5.zip"
SHA="913928416f54cc036df5df58ff1894093420ed4c4792a1d8844cfed7554d4808"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.5
bash run-on-aapanel.sh --check
# ONLY IF PRELIGHT PASSES:
bash run-on-aapanel.sh --probe PROBE_COMBINED_FILEINFO_ZIP_FUNCTIONAL_SMOKE_ONLY
```
Required success markers:
```ini
BARAMA_PHP_COMBINED_SMOKE_PREFLIGHT=PASS
BARAMA_SYNTHETIC_FILEINFO_ZIP_FUNCTIONAL_SMOKE=PASS
BARAMA_PHP_COMBINED_RUNTIME_SMOKE=PASS
TEMPORARY_SANDBOX=REMOVED
```

CodeCanyon FINAL remains ON HOLD until isolated Barama bootstrap, genuine signed-update install, and rollback acceptance complete.


## 2026-10-13: v1.0.5 failed before functional PHP execution — v1.0.6 repair

Observed on aaPanel for `Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.5.zip`:
- Archive integrity PASS; read-only preflight PASS.
- Offline tests 61/61 PASS; PHP core pin, 48 core ELF resolutions, 2 additional ELF resolutions and pinned `fileinfo.so`/`zip.so` hashes verified.
- Opt-in confined probe failed with `AttributeError: 'PosixPath' object has no attribute 'chown'` at `extension_probe.py:229` in `confined_inventory()`.
- It failed while configuring synthetic scratch access, **before launching the PHP combined functional smoke**. The prior accepted v1.0.4 individual extension loads are unaffected.
- **v1.0.5 superseded; do not rerun.**

### v1.0.6 correction — diagnostic only
Archive: `Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.6.zip`
SHA-256: `9dc8071f5fd57be9efd105d0cc642cc30704830205e11972842b416ffd2d40d1`
Size: 23537 bytes.

- Replace invalid `scratch.chown(65534, 65534)` with Linux-supported `os.chown(scratch, 65534, 65534, follow_symlinks=False)` after creating the **private tmpfs-only** scratch directory and rejecting symlinks; verify UID/GID 65534 and mode 0700 before synthetic PHP invocation.
- Add regression tests which execute actual `confined_inventory()` flow using mocked `mount`, `umount2`, `os.chown`, file copies and PHP child processes, verifying success, functional rejection and ownership-change failure. Real mount and PHP operations are **NOT performed in these tests**.
- Offline tests 66/66 PASS; Python/shell/synthetic PHP syntax PASS; fresh-extraction SHA256SUMS PASS; bad opt-in phrase rejected.
- No Barama source/config/DB, customer data, PHP INI, IMAP, signing keys, firewall or updater is included. Preserve mount/network/PID namespace isolation, tmpfs chroot, non-root UID/GID and PHP/extension SHA pins.
- **v1.0.6 live aaPanel probe NOT YET TESTED.** Even if the smoke passes, full Barama bootstrap and genuine signed update installation/rollback are still untested.

### aaPanel instructions
Upload v1.0.6 archive into `/root`:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP=Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.6.zip
SHA=9dc8071f5fd57be9efd105d0cc642cc30704830205e11972842b416ffd2d40d1
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.6
bash run-on-aapanel.sh --check
# Proceed only after preflight PASS:
bash run-on-aapanel.sh --probe PROBE_COMBINED_FILEINFO_ZIP_FUNCTIONAL_SMOKE_ONLY
```

Full acceptance requires `BARAMA_PHP_COMBINED_SMOKE_PREFLIGHT=PASS`, `BARAMA_SYNTHETIC_FILEINFO_ZIP_FUNCTIONAL_SMOKE=PASS`, `BARAMA_PHP_COMBINED_RUNTIME_SMOKE=PASS` and `TEMPORARY_SANDBOX=REMOVED`. CodeCanyon FINAL remains ON HOLD.


## 2026-10-13: v1.0.6 combined functional smoke accepted (aaPanel output)

User executed verified `Barama-PHP-INI-Free-Combined-Smoke-aaPanel-v1.0.6.zip` SHA `9dc8071f5fd57be9efd105d0cc642cc30704830205e11972842b416ffd2d40d1`.

Observed: 66/66 offline tests PASS; PHP binary pin PASS; 48 core ELF resolutions; 2 additional ELF resolutions; `fileinfo.so` SHA `4b4bfc5676233036cf4856c9ddc01c5274a1b5a6d004e313655a7dddfaef4360`, `zip.so` SHA `6a93dee35e7d42fd18bae63dfb48088404b3647d37080e06f9e22e7631382968`; IMAP excluded; production PHP INI unchanged per probe.

Verified output:
```ini
EPHEMERAL_MOUNT_NETWORK_PID_NAMESPACES=PASS
PHP_EXTENSION_EXECUTION_NONROOT_UID_GID=65534:65534
INI_FREE_BASELINE_MODULE_COUNT=46
EXTENSION_FILEINFO_ISOLATED_LOAD=PASS
EXTENSION_ZIP_ISOLATED_LOAD=PASS
BARAMA_SYNTHETIC_FILEINFO_ZIP_FUNCTIONAL_SMOKE=PASS
BARAMA_PHP_COMBINED_RUNTIME_SMOKE=PASS
TEMPORARY_SANDBOX=REMOVED
```

**Accepted for INI-free PHP runtime + synthetic combined extension functionality only.** Neither Barama application code nor staging database, private credentials, license, Publisher identity or updater was exercised.

## v1.0.7 — Disconnected bootstrap *static* source preflight (NEXT)

From canonical repository review:
- `index.php` checks `application/storage/app/installation/installed.php`, may redirect to installer/updater, then loads runtime/bootstrap and dispatches the application.
- `application/bootstrap/runtime.php` executes `RuntimeEnvironment::load()`, `Config::load()` and `session_start()`.
- `RuntimeEnvironment::load()` may `require` private installation/environment state, `Config::load()` executes PHP config files.
- `application/bootstrap/app.php` registers an exception handler which may create/write application logs on failure.
- Therefore directly requiring or running the full application is **not** an acceptable next no-effect probe.

Prepared self-contained aaPanel-only, no-PHP-execution static gate:
- ZIP: `Barama-Disconnected-Bootstrap-Static-Gate-aaPanel-v1.0.7.zip`
- SHA-256: `106709157590900dd5ca0560736a7f81ea7a09405fa21042653e3d2bf330179b`
- Private accepted source overlay: `/root/barama-private-v2-http-source-overlay` (fixed in diagnostic code; no alternative fallback).
- Reviews ten explicit nonsecret bootstrap source/version files and nonstate file names only, with path/ownership/symlink safety checks and static code-topology checks.
- Does **NOT** open installation/environment/license state content, read `.env`, walk `application/storage`, invoke PHP, connect to MySQL, perform network requests, run Barama, or alter files.
- 15/15 offline tests PASS; fresh-extraction checks PASS; bash syntax PASS.
- **Live aaPanel v1.0.7 source preflight NOT YET TESTED.** PASS permits planning the next confined bootstrap test; it does NOT authorize application execution.

Commands after uploading ZIP to `/root`:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP=Barama-Disconnected-Bootstrap-Static-Gate-aaPanel-v1.0.7.zip
SHA=106709157590900dd5ca0560736a7f81ea7a09405fa21042653e3d2bf330179b
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Disconnected-Bootstrap-Static-Gate-aaPanel-v1.0.7
bash run-on-aapanel.sh --check
```
Expected marker: `BARAMA_DISCONNECTED_BOOTSTRAP_STATIC_PREFLIGHT=PASS`. If absent, stop and review the mismatch. No `--probe` or `--deploy` exists in this kit.

CodeCanyon FINAL remains **ON HOLD**; signed update install and rollback are still NOT TESTED.
