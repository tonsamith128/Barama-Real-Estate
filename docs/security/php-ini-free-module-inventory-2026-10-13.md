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


## 2026-10-13: v1.0.7 static source gate outcome (aaPanel) — FAIL, no execution

Observed exact output: ZIP hash PASS, 15/15 offline unit tests PASS, then `STOP: SOURCE GATE FAILED: required source file missing: index.php` from the pinned private overlay at `/root/barama-private-v2-http-source-overlay`. This means **the fixed overlay root does not contain the expected root-level entrypoint**. It does not prove the previously accepted 629-file source inventory was lost, nor prove Barama is incompatible. No PHP, network, database, or updater execution was performed.

Do not copy `index.php` from production, run a fallback production bootstrap, modify the overlay or relax private root enforcement to make this check pass.

### v1.0.8 read-only private source layout diagnostic (NEXT)

Archive: `Barama-Private-Source-Layout-Diagnostic-aaPanel-v1.0.8.zip`
SHA-256: `1b26f434a1039791fe1ec29814a47c15f8233801f991c5e9517b98d14ef71622`
Size: 7877 bytes.

- Fixed, private, read-only source overlay: `/root/barama-private-v2-http-source-overlay`.
- A distinct, explicitly known private scaffold `/root/barama-isolated-runtime-scaffold/code` is checked for root-level entrypoint *presence by metadata only*. It is **not** selected as alternate source or executed.
- Bounded directory-entry scan under overlay, excluding `storage`, `.env*`, secrets, license/backups/cache/logs, vendor and node_modules. No file contents read.
- Reports root `index.php` presence, bounded nested entrypoint candidates, `application`/VERSION/bootstrap/routes metadata and nonstate file counts. Does not open environment or secrets.
- Refuses symlinked nonstate paths, unsafe candidate modes, hard-linked `index.php`, untrusted overlay root, unbounded enumeration.
- No production source or website access, no PHP, MySQL, network or updater.
- Running 18 offline tests creates and removes temporary fixtures under the host's normal temp directory; this is not a production/source modification.
- Fresh ZIP integrity, SHA256SUMS, shell syntax, 18/18 offline tests PASS locally. **Live aaPanel execution NOT TESTED.**
- The diagnostic `BARAMA_PRIVATE_SOURCE_LAYOUT_DIAGNOSTIC=PASS` is NOT a bootstrap acceptance marker.

### aaPanel

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP=Barama-Private-Source-Layout-Diagnostic-aaPanel-v1.0.8.zip
SHA=1b26f434a1039791fe1ec29814a47c15f8233801f991c5e9517b98d14ef71622
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Private-Source-Layout-Diagnostic-aaPanel-v1.0.8
bash run-on-aapanel.sh --check
```

Next decision: compare the reported topology against the accepted private 629-file inventory and private scaffold and prepare a **separately pinned** non-executing bootstrap source verification only after that reconciliation. CodeCanyon FINAL remains ON HOLD.


## 2026-10-13 — v1.0.8 private source layout diagnostic accepted (aaPanel)

User-provided server output:
- `Barama-Private-Source-Layout-Diagnostic-aaPanel-v1.0.8.zip` SHA verification PASS; 18/18 offline tests PASS.
- `PINNED_OVERLAY_ROOT=VERIFIED_PRIVATE`; nonstate overlay inventory: 630 files, 41 directories, 340 PHP files.
- Root entry point absent (`OVERLAY_ROOT_INDEX_PHP=ABSENT`); root-level `application`, `VERSION`, runtime/bootstrap/routes all absent.
- Nine `index.php` candidates, including **`source/index.php`**, `source/installer/index.php`, `source/updater/index.php`, and several templates under `source/application/resources/views`.
- `LAYOUT_CLASSIFICATION=NESTED_ENTRYPOINT_CANDIDATE_ONLY`.
- Separate `/root/barama-isolated-runtime-scaffold/code/index.php` found by *metadata only*. **Scaffold not selected as fallback.**
- `BARAMA_PRIVATE_SOURCE_LAYOUT_DIAGNOSTIC=PASS`; source bytes and secret/runtime state never read; no PHP/app/network/DB execution.
- The earlier 629-file accepted snapshot and 630-file v1.0.8 overlay nonstate count are not proven equivalent; **do not treat as a 1-file content drift without reconciliation**.

**Engineering conclusion:** the v1.0.7 static gate targeted the wrong root, not evidence of source loss. The next candidate application root is the exact nested `/root/barama-private-v2-http-source-overlay/source`; source completeness remains to be statically checked.

## v1.0.9 — Nested-source static bootstrap gate (next aaPanel check)

Artifact: `Barama-Disconnected-Bootstrap-Source-Static-Gate-aaPanel-v1.0.9.zip`

ZIP SHA-256: `396116535219806f34004a3a479a0261af4a8664df1813f73bbaf71d90afbbfb`

ZIP size: 8,309 bytes.

Changes against v1.0.7:
- Fix exact pinned application source to `/root/barama-private-v2-http-source-overlay/source`, **without fallback** to production webroot, scaffold or any other source location.
- Verify trusted, root-owned, nonsymlink private overlay, nested source root, and fixed parent chain. Reject untrusted writes, links, missing source files, malformed `VERSION`, and missing expected bootstrap code relationships.
- Read **only ten explicit, bounded non-secret source files** under nested source with `O_NOFOLLOW` and file-stat validation. Enumerate nonstate filenames with depth/file limits; skip storage, .env*, private/secrets/license/backups/logs/cache and other sensitive trees. Runtime state metadata intentionally NOT inspected.
- No application PHP executed; no SQL, staging DB, secrets, network, updater, signer, production INI or deployment operations.
- 25/25 offline local tests PASS; fresh extraction SHA256SUMS PASS; shell validation PASS; wrong approval mode rejected; unpinned build-host check safely fails closed.
- **v1.0.9 live aaPanel result: NOT YET TESTED.** A static structural PASS is not approval to execute application code.

aaPanel:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Disconnected-Bootstrap-Source-Static-Gate-aaPanel-v1.0.9.zip"
SHA="396116535219806f34004a3a479a0261af4a8664df1813f73bbaf71d90afbbfb"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Disconnected-Bootstrap-Source-Static-Gate-aaPanel-v1.0.9
bash run-on-aapanel.sh --check
```

Next gate on PASS: separate content provenance/hash reconciliation and side-effect review to design a confined disconnected application probe. Barama execution is NOT YET AUTHORIZED. Signed-update installation/rollback remain NOT TESTED. **CodeCanyon FINAL ON HOLD.**


## 2026-10-13 — v1.0.9 nested bootstrap static gate accepted on aaPanel

User-provided execution output verifies:
- ZIP integrity PASS; **25/25** offline tests PASS.
- `ACCEPTED_PRIVATE_SOURCE_OVERLAY=FOUND_AND_PRIVATE`; `AUTHORITATIVE_SOURCE_SUBDIRECTORY=source`.
- `SOURCE_VERSION=0.9.22-dev`; `NONSTATE_SOURCE_FILES=629`; `NONSTATE_PHP_FILES=340`; `NONSTATE_DIRECTORIES=40`; `STATIC_ROUTE_DECLARATIONS=243`.
- `STATIC_BOOTSTRAP_FILES_REVIEWED=10`, `KNOWN_BOOTSTRAP_SIDE_EFFECTS=SESSION_START,CONFIG_REQUIRE,ERROR_HANDLER_LOG`.
- `RUNTIME_STATE_METADATA=NOT_INSPECTED`, `RUNTIME_STATE_VALUES=NOT_READ`, `CUSTOMER_DB_AND_SECRETS=NOT_ACCESSED`, `STAGING_APPLICATION_EXECUTION=NOT_PERFORMED`.
- `BARAMA_NESTED_BOOTSTRAP_STATIC_PREFLIGHT=PASS`.
- Reconciliation: v1.0.8 counted 630 entries at the overlay wrapper level; v1.0.9 counted 629 within nested `source/`. This aligns counts in scope, **not a byte-identity proof**.

## v1.0.10 — read-only private nonstate source fingerprint + ten GitHub blob comparison (NEXT)

Package: `Barama-Private-Source-Provenance-Gate-aaPanel-v1.0.10.zip`
ZIP SHA-256: `e9965cb7550c58924d0fae84f3780754e7038e599f7bf739a1da54fdef26e8d1`
Size: 14,916 bytes.

The gate only uses `--check`. It re-runs v1.0.9 structural checks, then hashes the **nonstate** files in exact private `/root/barama-private-v2-http-source-overlay/source` using bounded `openat`/directory FDs, `O_NOFOLLOW`, owner/mode/type checks, no hard links, and mid-read consistency checks. No source file contents or complete filenames are exported. It explicitly skips hidden files, `storage`, private, secrets, licenses, backups, cache, logs and other excluded trees without opening them. No PHP execution or network operation.

Outputs: `NONSTATE_TREE_SHA256`, counts, byte totals, and Git *blob* content-ID comparison of the same **ten** nonsecret bootstrap files previously reviewed by v1.0.9 against pinned repository `tonsamith128/Barama-Real-Estate` commit `fc617c12790f7075ccfd0e418a1bba3443450941`. Git blob SHA-1 is used solely for Git content identity; SHA-256 is used for the full nonstate source fingerprint. It reports `TEN_FILES_MATCH` or `TEN_FILES_DIVERGE_REVIEW_REQUIRED` without hiding mismatches. Either result remains **partial provenance**, not proof of complete tree equality with GitHub or the previous private 629-file accepted source manifest (no prior tree digest available).

Build-local QA: 46/46 tests PASS; Python + shell syntax PASS; fresh extracted internal SHA256SUMS PASS; invalid opt-in mode rejected. **Live aaPanel execution NOT TESTED.**

aaPanel:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Private-Source-Provenance-Gate-aaPanel-v1.0.10.zip"
SHA="e9965cb7550c58924d0fae84f3780754e7038e599f7bf739a1da54fdef26e8d1"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Private-Source-Provenance-Gate-aaPanel-v1.0.10
bash run-on-aapanel.sh --check
```

Successful capture marker: `BARAMA_NONSTATE_SOURCE_INTEGRITY_CAPTURE=PASS`. Always inspect `BARAMA_BOOTSTRAP_REFERENCE_RESULT`; `BARAMA_SOURCE_PROVENANCE=PARTIAL_NOT_FINAL` is the intended limit.

No production webroot, INI, firewall, application PHP runtime, database, license, signing identity, genuine updater install, or rollback is accessed or executed. CodeCanyon FINAL **ON HOLD**.


## 2026-10-13 — v1.0.10 live acceptance: tree fingerprint PASS; reference divergence REQUIRES REVIEW

User-provided aaPanel transcript confirmed verified ZIP, 46/46 offline tests and exact private overlay identity. Source: `0.9.22-dev`; 629 nonstate files, 340 PHP files, 40 nonstate directories; 7,025,491 bytes; 243 static route declarations.

Accepted SHA-256 tree fingerprint:
`900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`

Pinned GitHub revision `fc617c12790f7075ccfd0e418a1bba3443450941`: 10 reviewed bootstrap Git blob comparisons, 7 matched, **3 differed**:
- `index.php`
- `application/routes/web.php`
- `application/app/Support/helpers.php`

Output `BARAMA_NONSTATE_SOURCE_INTEGRITY_CAPTURE=PASS`; `BARAMA_BOOTSTRAP_REFERENCE_RESULT=TEN_FILES_DIVERGE_REVIEW_REQUIRED`; `BARAMA_SOURCE_PROVENANCE=PARTIAL_NOT_FINAL`.

Reference-code inspection of `fc617c1` found **167 static route declarations** (`get=57, post=110, add=0`) against private `243` (count delta +76). This is NOT a precise added-route diff: removals/edits may exist. GitHub helpers baseline includes 23 function names. The three reference Git blob IDs are in the previously pinned v1.0.10 source gate.

**No source reconciliation, restore, merge, production change or bootstrap execution is authorized by this acceptance.**

## v1.0.11 — Bootstrap divergence static triage (NEXT)

ZIP: `Barama-Bootstrap-Divergence-Triage-aaPanel-v1.0.11.zip`
SHA-256: `c8c349df964b661f0647d2dbfd466852ec650b5f98cd1645eb939c6fed80f001`
Size: 20,672 bytes.

- Reuses v1.0.9 source topology inspection and v1.0.10 bounded, private nonstate source fingerprint.
- Requires **exact match** to the accepted tree SHA-256, counts, byte size, seven matched and exactly three divergent allowed files.
- Reads only those three fixed private nonsecret source files through directory-FD-anchored `O_NOFOLLOW` file operations, bounds, root ownership, no-hardlink, stable identity tests.
- Prints fixed path labels, Git blob IDs, source SHA-256, reference/current line and byte counts, GET/POST/add route declaration *counts*, presence of fixed GitHub helper-function names, and presence/order of seven `index.php` bootstrap anchor strings.
- Does **not** print private source lines, route paths, arbitrary names, secret values or file data.
- This package carries **reference structural metrics, not reference source bytes**; an exact line-by-line diff or full GitHub tree equivalence remains NOT TESTED.
- Only the fixed private source overlay `/root/barama-private-v2-http-source-overlay/source` is examined. No application PHP, DB, network, .env, runtime configuration, service, updater, signer or production change.
- 69/69 offline local tests PASS; bash/Python syntax PASS; fresh-extraction checksums PASS; invalid `--probe` rejected.
- **Live aaPanel v1.0.11 outcome: NOT YET TESTED.**

### aaPanel
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Bootstrap-Divergence-Triage-aaPanel-v1.0.11.zip"
SHA="c8c349df964b661f0647d2dbfd466852ec650b5f98cd1645eb939c6fed80f001"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Bootstrap-Divergence-Triage-aaPanel-v1.0.11
bash run-on-aapanel.sh --check
```

Expected diagnostic completion marker: `BARAMA_BOOTSTRAP_DIVERGENCE_TRIAGE=PASS`, alongside `BARAMA_SOURCE_PROVENANCE=PARTIAL_RECONCILIATION_REQUIRED`. This only accepts the read-only triage; it does not mean a source version has been approved.

If fingerprint or trust controls do not match, STOP without weakening confinement or copying code.

**CodeCanyon FINAL: ON HOLD.** Confined disconnected bootstrap, genuine signed-update install and rollback are NOT TESTED.


## 2026-10-13 — v1.0.11 aaPanel static divergence triage accepted

User-provided transcript:
- Verified ZIP SHA-256 `c8c349df964b661f0647d2dbfd466852ec650b5f98cd1645eb939c6fed80f001`; 69/69 local offline tests PASS on aaPanel.
- Revalidated private tree fingerprint `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.
- Exactly three known divergences against GitHub pinned commit `fc617c12790f7075ccfd0e418a1bba3443450941`:
  - `index.php`: 3,376 private bytes / 2,344 reference, 115 / 89 lines; private Git blob `9435eba90c2229729b5d8c59232c617d8ec9450f`.
  - `application/routes/web.php`: 48,478 / 30,721 bytes, 1,130 / 753 lines; private Git blob `bf6aec013015b4d116f2b4dbaaaae37be9f029b7`.
  - `application/app/Support/helpers.php`: 19,197 / 19,181 bytes, 508 / 509 lines; private Git blob `00d1f03cb773097caa2fa023864a81ae5c80c61a`.
- Static route declaration counts: private 243 (94 GET, 149 POST), GitHub 167 (57 GET, 110 POST), net count +76. **Not proof of precisely 76 added routes.**
- All 23 fixed GitHub helper names remain present; all seven static entrypoint markers were present in their reference order.
- `BARAMA_BOOTSTRAP_DIVERGENCE_TRIAGE=PASS`, `BARAMA_SOURCE_PROVENANCE=PARTIAL_RECONCILIATION_REQUIRED`.
- No application execution, SQL, network, production writes, or signed update performed. No source content emitted.

## v1.0.12 — Read-only, pinned three-file lexical risk comparison (NEXT)

Artifact: `Barama-Bootstrap-Semantic-Risk-Gate-aaPanel-v1.0.12.zip`

ZIP SHA-256: `42cb681424ca95fb79884043da7a43baec1a2014aebf9438413a3f9c88c70f67`

Size: 25,729 bytes.

Contents: carries unchanged v1.0.9-v1.0.11 static verification modules, a new `bin/semantic_risk_gate.py`, four test suites, an internal SHA256SUMS.txt and `run-on-aapanel.sh --check` only. All 90/90 offline tests PASS; Python/shell syntax PASS; internal manifest and fresh extraction PASS; rejects `--probe`.

This gate validates the **exact** pinned 629-file tree fingerprint and three bootstrap-divergence identities before reading approved source bytes. The three selected private, non-secret files are opened with previous O_NOFOLLOW + root-mode and stable-stat checks. The report compares aggregate lexical indicators to counters fetched from the exact GitHub commit `fc617c1`: include/require, eval/process calls, session, header, file I/O, PDO/curl/stream, request/server globals, env function calls, function definitions and routes. It also reports literal route declaration/duplicate/unclassified counts, **not route paths**, and alerts to increased lexical counts for potentially effectful operations.

These are **lexical/regex counts**, not PHP AST validation, not semantic equivalence, not comprehensive exploit review and not a route-by-route diff. Strings and comments can affect counts. A zero risk-counter delta is not approval to run an application. Output **always** includes `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`.

No production webroot, services, PHP INI, private storage, customer database, network, signing identity, updater install, or rollback affected.

### aaPanel execution
Upload ZIP to `/root`, then:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Bootstrap-Semantic-Risk-Gate-aaPanel-v1.0.12.zip"
SHA="42cb681424ca95fb79884043da7a43baec1a2014aebf9438413a3f9c88c70f67"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Bootstrap-Semantic-Risk-Gate-aaPanel-v1.0.12
bash run-on-aapanel.sh --check
```

Required diagnostic completion: `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `BARAMA_BOOTSTRAP_LEXICAL_RISK_TRIAGE=PASS`, `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`.

**v1.0.12 live aaPanel execution NOT TESTED.** CodeCanyon FINAL remains ON HOLD; true source-level reconciliation, confined disconnected bootstrap, genuine signed-update installation and rollback still pending.


## 2026-10-13 — v1.0.12 aaPanel lexical triage accepted, source review still open

User-provided v1.0.12 transcript:
- ZIP SHA-256 verified: `42cb681424ca95fb79884043da7a43baec1a2014aebf9438413a3f9c88c70f67`
- 90/90 offline tests PASS; full private nonstate source fingerprint revalidated:
  `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.
- Exactly three divergent files checked: `index.php`, `application/routes/web.php`, `application/app/Support/helpers.php`.
- `index.php`: 4 lexical include/require occurrences vs 2 in GitHub reference. No increase in specified process-execution, dynamic eval, direct PDO, file-write, session-start, curl or stream counters. **Actual include targets/side effects not verified.**
- Private route declarations: 243 vs GitHub 167. All 243 were literal according to the bounded lexical scanner; no duplicates or unclassified expressions. **Actual handler and middleware behavior not reviewed.**
- Helpers: 23 function declarations on each side, unchanged aggregate environment access counts.
- `RISK_SENSITIVE_LEXICAL_COUNT_INCREASES=0`; `BARAMA_BOOTSTRAP_LEXICAL_RISK_TRIAGE=PASS`; `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`; `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`.
- These are regex-based lexical indicators, not PHP AST/control flow findings, absence-of-risk proof, or an authorized application startup.
- No production or staging code changes, SQL, network, updater, signer, or app PHP execution.

## v1.0.13 — exact literal route signature comparison, read-only (NEXT)

Package: `Barama-Route-Signature-Reconciliation-aaPanel-v1.0.13.zip`
SHA-256: `b4b2b4ca309acbf9c8df5e8dc183d1c667c404b1fd717579b115634336768a4a`
Size: 38,042 bytes.

Changes/scope:
- Preserves the v1.0.9 through v1.0.12 static preflight logic, exact private root and fingerprint guard, source ownership and no-follow checking, and fixed three-file comparison.
- Includes a SHA-256-pinned reference manifest of **167** GitHub literal METHOD+PATH signatures from revision `fc617c12790f7075ccfd0e418a1bba3443450941`, route file Git blob `236468ff78a8f67b1662fc9df6d9e54cba90ff6c`.
- Reference signatures are in the kit as digests, not raw paths. Private METHOD+PATH signatures are hashed in memory; no private route paths/source lines are printed.
- Compares exact shared/private-only/reference-only signatures by GET and POST; reports relative order preservation among shared signatures and whether the known reference public fallback is the last private GET declaration.
- Rechecks lexical entrypoint include count 4 vs 2 without interpreting include targets or their side effects.
- Explicitly does **not** compare route handlers, middleware, auth policy, PHP AST, runtime side effects, or complete source equivalence; does **not** execute app/PHP, touch database or runtime state, or authorize bootstrap.
- Fresh ZIP extraction SHA256SUMS PASS; syntax PASS; **112/112 offline unit tests PASS**; wrong `--probe` approval rejected. **Live aaPanel v1.0.13: NOT TESTED.**
- If the pinned source tree or private Git blob IDs differ, the kit must STOP; do not re-pin the source merely to obtain PASS.

### aaPanel instructions
Upload to `/root` and run:

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Route-Signature-Reconciliation-aaPanel-v1.0.13.zip"
SHA="b4b2b4ca309acbf9c8df5e8dc183d1c667c404b1fd717579b115634336768a4a"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Route-Signature-Reconciliation-aaPanel-v1.0.13
bash run-on-aapanel.sh --check
```

Expected diagnostic markers:
```ini
PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS
PRIVATE_ROUTE_DECLARATIONS=243
REFERENCE_ROUTE_DECLARATIONS=167
BARAMA_ROUTE_SIGNATURE_RECONCILIATION_TRIAGE=PASS
BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED
DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED
CODECANYON_FINAL=ON_HOLD
```

A diagnostic PASS is **not** a source reconciliation approval. NEXT: interpret shared/new/removed/order results, review additional entrypoint includes and actual middleware and access control before designing any confined disconnected PHP application execution. Genuine signed update installation/rollback remains mandatory before CodeCanyon FINAL.


## 2026-10-13 — v1.0.13 route signature reconciliation accepted (user aaPanel log)

User provided live diagnostic transcript for verified `Barama-Route-Signature-Reconciliation-aaPanel-v1.0.13.zip` (`b4b2b4ca309acbf9c8df5e8dc183d1c667c404b1fd717579b115634336768a4a`). All 112 offline tests PASS; exact accepted private source fingerprint remains `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.

Exact METHOD+literal-route-path signature comparisons against the pinned reference `fc617c12790f7075ccfd0e418a1bba3443450941`, route blob `236468ff78a8f67b1662fc9df6d9e54cba90ff6c`:
- GET: common 57, private-only 37, reference-only **0**, shared relative order preserved.
- POST: common 110, private-only 39, reference-only **0**, shared relative order preserved.
- Public GET fallback `/{slug}` remains final private GET route.
- Thus 167/167 reference signatures preserved and **76 exact private-only signatures** (not merely an aggregate count delta).
- Private entrypoint has four include/require lexical occurrences vs reference two. Their actual targets, control flow and side effects remain **NOT TESTED**.
- Route handler, middleware, access control and PHP AST semantics remain **NOT TESTED**.
- `BARAMA_ROUTE_SIGNATURE_RECONCILIATION_TRIAGE=PASS`; `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`; `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`.
- No app, SQL, network, production or source writes were performed.

## v1.0.14 — Bootstrap include boundary and route precedence static triage (NEXT)

Archive: `Barama-Bootstrap-Include-Route-Precedence-aaPanel-v1.0.14.zip`
SHA-256: `7fcb5b1fe917f4187741988606d293fa0fb16d6a40ea2b6e9b3a2e89f8427274`
Size: 44,256 bytes.

Continues the exact static validation chain from v1.0.9–v1.0.13:
- Fixed private source `/root/barama-private-v2-http-source-overlay/source` and previously accepted full SHA-256 tree fingerprint; ownership/no-symlink/hardlink controls. Revalidates all three pinned file blobs, lexical counters and exact 167 reference signatures shared in order.
- Reads the same **three** previously accepted nonsecret bootstrap files; no runtime state, `.env`, license secrets, database or customer data.
- Classifies the four lexical PHP `index.php` include/require occurrences into previously known runtime/app bootstrap fragments and *other*; reports hashed expression fingerprints, static positions relative to installation/version-guard anchor text, and dynamic/URI stream lexical indicators. Does not print include targets or source strings.
- Checks whether any newly inserted private route's static template **could overlap** with a retained reference route registered later (possible first-match shadow), and vice versa, with per-method aggregate counts but no private paths.
- Models pinned `Router::match` only at the level of literal segment vs `{parameter}` template intersections. **Not** a PHP AST, handler/middleware/access-control audit or runtime router simulation. Regex may count text in comments/strings; false negatives/positives possible; all results are diagnostic only.
- Offline 138/138 tests PASS, fresh ZIP extraction SHA256SUMS PASS, bash syntax PASS, wrong `--probe` rejected, missing pinned private source fails closed on build host. **Live aaPanel execution NOT YET TESTED**.
- No PHP application execution, network, SQL, production services/INI changes, staging source mutation, updater, signing identity or installer touched.

aaPanel command:
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Bootstrap-Include-Route-Precedence-aaPanel-v1.0.14.zip"
SHA="7fcb5b1fe917f4187741988606d293fa0fb16d6a40ea2b6e9b3a2e89f8427274"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Bootstrap-Include-Route-Precedence-aaPanel-v1.0.14
bash run-on-aapanel.sh --check
```

Expected diagnostic markers: `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `EXAMINED_ENTRYPOINT_INCLUDES=4`, `PRIVATE_ONLY_ROUTE_SIGNATURES=76`, `BARAMA_BOOTSTRAP_BOUNDARY_TRIAGE=PASS`, `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`. If any identity check fails, STOP; no fallback, re-pinning, source editing or bypass.

This diagnostic is **not** source-reconciliation acceptance. Real Barama disconnected bootstrap and genuine signed-update install/rollback remain NOT TESTED. CodeCanyon FINAL **ON HOLD**.


## 2026-10-13 — v1.0.14 aaPanel include boundary / route precedence diagnostic accepted

User-provided transcript verified ZIP `7fcb5b1fe917f4187741988606d293fa0fb16d6a40ea2b6e9b3a2e89f8427274`, 138/138 offline tests PASS, private tree SHA-256 unchanged:
`900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.

**Actual observations:**
- `EXAMINED_ENTRYPOINT_INCLUDES=4`. TWO new/other lexical `include/require` expressions appear **BEFORE_INSTALLATION_GUARD**, expression IDs `080cedfd5cb5f1ae` and `36dbd8704972f94b`. Neither had flagged dynamic or URI/stream indicators. Their target files and side effects were **not** resolved, opened or evaluated.
- Known runtime include appears before the version guard; known application bootstrap include appears after it.
- All 76 private-only route signatures remain. Potential new-before-shared template-overlap pairs: **GET 12** affecting **4** shared GET signatures, and **POST 2** affecting **2** shared POST signatures. The script reported zero old-before-new overlapping pairs.
- No PHP AST, handler/middleware access control, include target verification, bootstrap execution, SQL, network or production writes.
- `BARAMA_BOOTSTRAP_BOUNDARY_TRIAGE=PASS` accepts diagnostic completion **only**. `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD` remain in force.
- Possible route shadowing is a static first-match template intersection, **not** an observed authentication or routing failure.

## v1.0.15 — pinned static early-include target classification and route impact (next)

Package `Barama-Early-Include-Route-Impact-aaPanel-v1.0.15.zip`

**External ZIP SHA-256:** `5d8f2a032afc6ec091b0fce696db4e423af2c0ea077ea71135192b32bc767ef3`

Size **56,778 bytes**; 19 internal package files. Build-local and fresh-extraction **159/159 offline tests PASS**, internal SHA256SUMS PASS, shell/Python syntax PASS, unapproved `--probe` rejected.

Package scope:
- Revalidates exact root-owned private `/root/barama-private-v2-http-source-overlay/source`, its pinned source fingerprint and all previously accepted gate identities. Does not use any fallback webroot.
- Performs a **bounded lexical** classification of the two early unknown include/require expressions. A simple `__DIR__ . '/static/path.php'` may be categorized by relative nonstate folder and emits only a truncated path hash; traversal, sensitive and excluded paths are NOT opened. Dynamic/unrecognized forms report `UNRESOLVED_EXPRESSION_NOT_OPENED`. **No included target file is opened and no PHP is executed.**
- Adds a pinned SHA256 manifest mapping the 167 *publicly known GitHub* route digests to broad `PUBLIC/ACCOUNT/DEVELOPER/DASHBOARD` categories (raw reference route paths are not included). Reports the **14** already identified possible earlier-new/later-shared route overlap pairs by method, reference category, truncated hashes and static template coverage (`NEW_COVERS_SHARED_TEMPLATE`, `SHARED_COVERS_NEW_TEMPLATE`, `EQUIVALENT_TEMPLATE_LANGUAGE`, or `PARTIAL_TEMPLATE_OVERLAP`). **Private route paths and source contents are not printed.**
- This is NOT a real PHP AST/control-flow, include side effect or middleware review. It does not authorize application bootstrap, touch runtime secrets/storage, PHP INI, production webroot, SQL, remote network, updater or signer.

aaPanel command after uploading the ZIP to `/root`:

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Early-Include-Route-Impact-aaPanel-v1.0.15.zip"
SHA="5d8f2a032afc6ec091b0fce696db4e423af2c0ea077ea71135192b32bc767ef3"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Early-Include-Route-Impact-aaPanel-v1.0.15
bash run-on-aapanel.sh --check
```

Expected completion:
```ini
PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS
EARLY_ENTRYPOINT_INCLUDE_COUNT=2
INCLUDE_TARGET_FILES_OPENED=0
ROUTE_PRECEDENCE_STATIC_COLLISION_PAIRS=14
BARAMA_EARLY_INCLUDE_ROUTE_IMPACT_TRIAGE=PASS
DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED
CODECANYON_FINAL=ON_HOLD
```

**Live v1.0.15 aaPanel result: NOT YET TESTED.** The remaining release path requires a targeted review of the real include targets, route handler/middleware access control, strictly confined disconnected application bootstrap, and actual signed-update install/rollback. Production changes are not authorized; CodeCanyon FINAL ON HOLD.


## 2026-10-13 — v1.0.15 early include + route impact aaPanel acceptance (diagnostic only)

The user provided the aaPanel output for the verified v1.0.15 ZIP `5d8f2a032afc6ec091b0fce696db4e423af2c0ea077ea71135192b32bc767ef3`, with 159/159 offline tests PASS and source fingerprint unchanged `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.

Findings:
- **2 includes occur before installation guard**, with expression IDs `080cedfd5cb5f1ae` and `36dbd8704972f94b`. Both forms `OTHER_EXPRESSION`; `UNRESOLVED_EXPRESSION_NOT_OPENED`. Dynamic indicators and URI/stream indicators were not detected by the lexical scanner; that is **NOT** a resolution of include target or side effects.
- **14** possible route-template overlap pairs: **PUBLIC 6, DEVELOPER 1, DASHBOARD 7, ACCOUNT 0**.
- Every pair classified `SHARED_COVERS_NEW_TEMPLATE`. This denotes a **narrower new route appearing before a broader retained reference template**, which may be an intentional exception; it does NOT prove an authorization bypass or safe middleware.
- `INCLUDE_TARGET_FILES_OPENED=0`; `ROUTE_HANDLER_MIDDLEWARE_AUTHORIZATION=NOT_TESTED`; `APPLICATION_PHP_SQL_NETWORK=NOT_EXECUTED`; `SOURCE_AND_PRODUCTION_WRITES=NONE`.
- `BARAMA_EARLY_INCLUDE_ROUTE_IMPACT_TRIAGE=PASS` means the static diagnostic completed. `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`; `CODECANYON_FINAL=ON_HOLD`.

## v1.0.16 — Additional include-shape and explicit route middleware diagnostic (next gate)

Artifact: `Barama-Preinstall-Include-Route-Middleware-aaPanel-v1.0.16.zip`
SHA-256: `158e1224bc20d8860840b294fba0fbbde4404f16a7823cb58b5b96de6caa7639`
Size: **63,674 bytes**.

- Fixed private source `/root/barama-private-v2-http-source-overlay/source`, pinned 629-file SHA-256 `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.
- Reuses the v1.0.9–v1.0.15 read-only gate chain, including exact three divergent-file identities, 167 GitHub reference route digests, path trust/bounds and never opening secrets or private runtime state.
- Adds a bounded lexical analysis of the **two early include-expression grammatical shapes** (require/include, once flag, `__DIR__`, `dirname`, variables, literals, concatenation and conditional indicators) without printing include path/target, opening a target file or evaluating PHP.
- Parses route-call argument boundaries from the previously accepted 243 literal declarations using a bounded string/comment-aware parenthesis splitter; for the same 14 potential overlap pairs reports earlier **new** and later **shared** explicit middleware flags and whether an earlier new declaration may omit an explicit Auth/Permission middleware present on the later shared declaration. Known `$guest`, `$guestPost` and `$csrf` aliases are only considered known if their expected assignment appears. Unresolved/unknown expressions are flagged UNKNOWN.
- **Explicit declaration metadata only**: handlers and middleware implementations, controller-internal authorization, runtime router behavior, actual include paths/side effects and PHP AST/control flow are **NOT TESTED**. Lexical presence or absence is not an authorization verdict.
- No code, route path, included target, secrets or runtime state is printed; only categories and truncated signature digests.
- Build-local and **fresh-extraction** package QA: 176/176 offline tests PASS (including synthetic full-243-route parser test), 20 internal file SHA256SUMS checks PASS, Python and shell syntax PASS; `--probe` rejected; no-fallback private-source guard stopped correctly outside aaPanel.
- **v1.0.16 live aaPanel execution NOT YET TESTED.** This diagnostic does not execute PHP, access SQL/network or modify production/staging source, signing identities or updater.

### aaPanel command

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Preinstall-Include-Route-Middleware-aaPanel-v1.0.16.zip"
SHA="158e1224bc20d8860840b294fba0fbbde4404f16a7823cb58b5b96de6caa7639"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Preinstall-Include-Route-Middleware-aaPanel-v1.0.16
bash run-on-aapanel.sh --check
```

Expected diagnostic completion markers: `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `EARLY_INCLUDE_EXPRESSION_COUNT=2`, `POTENTIAL_ROUTE_OVERLAP_PAIRS=14`, `BARAMA_INCLUDE_MIDDLEWARE_STATIC_TRIAGE=PASS`, `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.

Do not treat the hypothetical `ROUTE_MIDDLEWARE_POTENTIAL_STATIC_GAP` count as a confirmed security vulnerability, nor a zero count as a safety guarantee. The next gate will require real include-target resolution and guarded middleware/handler review before any confined bootstrap execution.

**CodeCanyon FINAL remains ON HOLD** until complete source/side-effect acceptance plus genuine signed-update installation and rollback.


## 2026-10-13 — v1.0.16 aaPanel accepted: early include variable forms and route middleware declarations

User-provided transcript confirms verified `Barama-Preinstall-Include-Route-Middleware-aaPanel-v1.0.16.zip` (SHA-256 `158e1224bc20d8860840b294fba0fbbde4404f16a7823cb58b5b96de6caa7639`), all 176 offline tests PASS, and previous 629-file source tree SHA-256 unchanged:
`900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.

Verified findings:
- Two additional early `REQUIRE` statements are `_once=YES`, use PHP **variables** rather than direct literal paths, and appear **before** the installation guard.
- For each expression: `HAS_DIR_MAGIC=NO`, `HAS_DIRNAME=NO`, `HAS_LITERAL=NO`, `HAS_CONCAT=NO`, `HAS_CONDITIONAL=NO`. Target resolution `UNRESOLVED_EXPRESSION_NOT_OPENED`; **no included PHP file was opened or executed**.
- 14 known earlier-new/later-retained route-template intersections passed bounded explicit-middleware review. `ROUTE_MIDDLEWARE_POTENTIAL_STATIC_GAP=0`, `ROUTE_MIDDLEWARE_UNKNOWN_MIDDLEWARE=0`, `ROUTE_MIDDLEWARE_NOT_INDICATED=14`. Static declarations appear to preserve the corresponding reference Auth/Permission flags, where present; no handler/control-flow authorization assessment was performed.
- For public routes, some earlier new routes have additional Auth or Guest middleware compared with the unprotected shared fallback; absence of gaps should not be confused with runtime access-control proof.
- `BARAMA_INCLUDE_MIDDLEWARE_STATIC_TRIAGE=PASS`, `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.
- Production/staging source writes, app PHP execution, network and database access: **NONE reported**.

## v1.0.17 — Read-only early include variable binding trace (NEXT)

ZIP: `Barama-Preinstall-Include-Binding-Trace-aaPanel-v1.0.17.zip`
**SHA-256:** `7abe2631c719382c27884dc0d6c448e70cf8c1540b408e69c66be5d7096e1484`
Size: **69,454 bytes**.

- Retains the exact pinned private Barama nonstate source `/root/barama-private-v2-http-source-overlay/source`, the full accepted source SHA-256 above and existing root-owned, O_NOFOLLOW, trusted-file 3-file review.
- New `bin/preinstall_bindings.py` performs strictly read-only **lexical** scanning of the approved `index.php` content. For each of the two identified `require_once $variable;` expressions, it searches **only prior assignments in that same file**; classifies unbound/ambiguous/conditional or nested assignments, simple `__DIR__ . '/local.php'` candidates and up to 3 levels of variable aliases. It checks only the grammar of possible candidate paths: excluded private storage, secrets, traversal and dangerous path shapes never become approved candidates.
- Emits truncated SHA256 IDs and classification labels only — **no PHP variable names, include target paths, source strings or file content**.
- No target PHP file is opened, statted or executed. A valid-looking static candidate is **NOT** proof the assignment is reachable, target exists, target contents are safe, or bootstrap may run. The scanner intentionally does not implement full PHP AST/control flow/heredocs and is diagnostic only.
- It does not access the production site, source outside pinned nonstate overlay, PHP INI/runtime, credentials, MySQL, external network, license/updater/signing keys or customer data. No code modification/installation.
- **199/199 offline tests PASS** after fresh extraction. Internal file SHA256SUMS PASS; Python/shell syntax PASS; `--probe` rejected; missing private source causes safe STOP in build container.
- **Live v1.0.17 aaPanel result: NOT YET TESTED.** All existing release prohibitions remain in place.

Command after uploading ZIP to `/root`:

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Preinstall-Include-Binding-Trace-aaPanel-v1.0.17.zip"
SHA="7abe2631c719382c27884dc0d6c448e70cf8c1540b408e69c66be5d7096e1484"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Preinstall-Include-Binding-Trace-aaPanel-v1.0.17
bash run-on-aapanel.sh --check
```

Successful **diagnostic** completion requires `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `EARLY_REQUIRE_ONCE_VARIABLE_SITES=2`, `INCLUDE_TARGET_FILES_OPENED=0` and `BARAMA_PREINSTALL_BINDING_TRACE=PASS` while retaining `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.

Next: evaluate binding classifications for a targeted static target/content and actual handler authorization review inside independent confinement, before any runtime bootstrap. Signed update/rollback still NOT TESTED.


## 2026-10-13 — v1.0.17 early include binding trace accepted on aaPanel (diagnostic only)

User-provided aaPanel transcript:
- Verified external v1.0.17 package SHA-256 `7abe2631c719382c27884dc0d6c448e70cf8c1540b408e69c66be5d7096e1484`.
- 199/199 offline unit tests PASS.
- Exact accepted 629-file source fingerprint revalidated: `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.
- Exactly two early `require_once $variable` sites before the installer guard.
  - First site `080cedfd5cb5f1ae`: `MULTIPLE_ASSIGNMENTS_UNRESOLVED`; **2 prior assignment candidates**, assignment scope UNKNOWN, no static target chosen.
  - Second site `36dbd8704972f94b`: `STATIC_CANDIDATE_APPLICATION_NONSTATE_PHP` from **one** prior top-level lexical assignment; candidate hash `db9cf6767bf6178b`. Target file existence/content/side effects **not verified**.
- `INCLUDE_TARGET_FILES_OPENED=0`; `BARAMA_PREINSTALL_BINDING_TRACE=PASS`; `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`; `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`; `CODECANYON_FINAL=ON_HOLD`.
- No PHP application, SQL/network, uploader/updater or production modification.

## v1.0.18 — Read-only early include assignment site classification and target *metadata* (NEXT)

ZIP: `Barama-Preinstall-Assignment-Candidate-Metadata-aaPanel-v1.0.18.zip`
ZIP SHA-256: `fadbe114f5cd6edd201756c70687679c1916bfa1be33a6eea7ac4e9337e2e48d`
Size: **76,266 bytes**. New module: `bin/preinstall_assignment_metadata.py`.

- Retains the prior v1.0.9–v1.0.17 read-only source checks, exact private `/root/barama-private-v2-http-source-overlay/source`, accepted source SHA-256 above and pinned 3-file identity constraints.
- New gate inspects only approved **`index.php`** text already read by prior triage; enumerates the two prior assignments to the first early include variable and the one assignment to the second. Reports assignment ordering, a lexical nesting class, RHS syntactic class and 16-char SHA-256 identifiers. Does **not** print variable names, RHS source, or candidate paths.
- If and only if a RHS is exactly a safe, static `__DIR__ . '/application/...php'` nonstate path, it may inspect the **target's metadata only**, with strict allowlisted path grammar and `O_NOFOLLOW` anchored parent directory FDs, final `stat(..., follow_symlinks=False)`, trust/owner/mode/type/nlink/size checks. **Target PHP file content is not opened/read or executed**; sensitive and runtime-state paths are excluded. Filesystem-related errors are redacted.
- This is lexical and does NOT resolve PHP control flow or select one of the first site's two assignments. Target metadata presence does not imply content safety. App bootstrap, handler/middleware authorization, signed updater install and rollback NOT TESTED.
- Offline/fresh ZIP QA: 226/226 tests PASS, 24 SHA256SUMS entries PASS, bash/Python syntax PASS, unapproved `--probe` returns exit 2, absent pinned source returns exit 1 safely. **Live v1.0.18 aaPanel: NOT YET TESTED.**

### aaPanel
```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Preinstall-Assignment-Candidate-Metadata-aaPanel-v1.0.18.zip"
SHA="fadbe114f5cd6edd201756c70687679c1916bfa1be33a6eea7ac4e9337e2e48d"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Preinstall-Assignment-Candidate-Metadata-aaPanel-v1.0.18
bash run-on-aapanel.sh --check
```

Expected diagnostic completion: `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `EARLY_REQUIRE_ONCE_VARIABLE_SITES=2`, `PRIOR_ASSIGNMENTS_1=2`, `PRIOR_ASSIGNMENTS_2=1`, `INCLUDE_TARGET_FILE_CONTENTS_OPENED=0`, `BARAMA_PREINSTALL_ASSIGNMENT_METADATA_TRIAGE=PASS`, `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.

No source re-pinning, production webroot fallback, PHP execution, license secret access, or installation. The next task is to review these syntactic assignment/metadata outcomes and perform a separately authorized static review of any positively identified nonsecret target files before designing a genuine isolated app bootstrap. CodeCanyon FINAL still ON HOLD.


## 2026-10-13 — v1.0.18 accepted aaPanel metadata audit: two missing-parent source candidates

User-supplied aaPanel log:
- Correct v1.0.18 package SHA-256 `fadbe114f5cd6edd201756c70687679c1916bfa1be33a6eea7ac4e9337e2e48d` PASS.
- **226/226 offline tests** PASS; exact full private nonstate source fingerprint still `900d5125852765df420537a42a50d5c208582bbab1dbfdb7a4b62b02fa3fa687`.
- Preinstallation `require_once` site 1: **two** prior assignment expressions. Assignment 1 TOP_LEVEL_LEXICAL `STATIC_CANDIDATE_APPLICATION_NONSTATE_PHP`, candidate ID `ece1e4aec6c25c82`, target status **`MISSING_PARENT_METADATA_ONLY`**. Assignment 2 NESTED_LEXICAL `FUNCTION_OR_SIDE_EFFECT_DEPENDENT`, target unresolved and not inspected.
- Site 2: **one** TOP_LEVEL_LEXICAL `STATIC_CANDIDATE_APPLICATION_NONSTATE_PHP`, candidate ID `db9cf6767bf6178b`, target status **`MISSING_PARENT_METADATA_ONLY`**.
- `STATIC_TARGET_METADATA_CHECKS=2`; `INCLUDE_TARGET_FILE_CONTENTS_OPENED=0`; no PHP/SQL/network/updater, no production or source writes.
- v1.0.18 returned `BARAMA_PREINSTALL_ASSIGNMENT_METADATA_TRIAGE=PASS`, but **did not resolve or approve either include**, proving neither real execution path nor side-effect safety. `BARAMA_SOURCE_RECONCILIATION=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.

**Security decision:** Do not copy missing candidate dependencies from production, create placeholder files, re-pin the accepted source, or execute the application merely to suppress the missing-parent result. Need to distinguish the absent parent boundary at each candidate, then review verified source-of-truth and real control flow.

## v1.0.19 — pinned missing dependency parent chain metadata diagnostic (NEXT)

Package: `Barama-Preinstall-Dependency-Chain-aaPanel-v1.0.19.zip`.

External ZIP SHA-256: `b7969605bcf9850c155440eb1727de5e487262a7790f2d94074c2f59088e91b2`; **80,205 bytes**.

- Exact trusted private root: `/root/barama-private-v2-http-source-overlay/source`; revalidates v1.0.9 static topology, v1.0.10–v1.0.18 pinned source and three-file identities, exact 629-file SHA-256 and the previously accepted 2/1 variable assignment inventory.
- For only the two previously identified static application PHP candidate IDs `ece1e4aec6c25c82` and `db9cf6767bf6178b`, follows **directory metadata only**, using directory file-descriptor nofollow traversal under pinned source, with owner/mode/device restrictions. Finds number of existing prefix directories, position (level) of first missing parent, and truncated hash of missing parent prefix to compare whether both candidates fail in the same subtree. Does **not print private path strings, variable names or source lines**.
- If a path unexpectedly no longer has a missing parent, STOP; do not silently accept a mutated source path or grant bootstrap permission.
- Does not open/read any target PHP file contents. No actual PHP AST/control flow, module import resolution, handler auth, app runtime, SQL, service, network, updater, signer or production modifications.
- Local **248/248 offline tests** PASS and fresh ZIP re-extraction **248/248** PASS; internal SHA256SUMS PASS; bash and Python syntax PASS; unapproved `--probe` rejected, absent private root fails closed. **Live aaPanel v1.0.19 NOT YET TESTED**.

aaPanel:

```bash
set -Eeuo pipefail
umask 077
cd /root
ZIP="Barama-Preinstall-Dependency-Chain-aaPanel-v1.0.19.zip"
SHA="b7969605bcf9850c155440eb1727de5e487262a7790f2d94074c2f59088e91b2"
echo "$SHA  $ZIP" | sha256sum -c -
unzip -oq "$ZIP" -d /root
cd /root/Barama-Preinstall-Dependency-Chain-aaPanel-v1.0.19
bash run-on-aapanel.sh --check
```

Expected on unchanged source: `PINNED_PRIVATE_SOURCE_FINGERPRINT_REVALIDATED=PASS`, `STATIC_CANDIDATE_PATHS_CHECKED=2`, each `CANDIDATE_N_STATUS=MISSING_PARENT`, `BARAMA_PREINSTALL_DEPENDENCY_CHAIN_DIAGNOSTIC=PASS`, `BARAMA_MISSING_PREINSTALL_SOURCE_DEPENDENCIES=REVIEW_REQUIRED`, `DISCONNECTED_BOOTSTRAP_EXECUTION=NOT_AUTHORIZED`, `CODECANYON_FINAL=ON_HOLD`.

A diagnostic PASS does **not** show that required dependencies can be safely restored or that either PHP include would execute. Next is source-of-truth recovery planning and targeted static review of branch control flow without using production source as fallback; genuine disconnected bootstrap and signed update/rollback remain untested.
