# Barama PHP 8.5 INI-free module inventory — 2026-10-13

## Purpose
Continue after the accepted PHP 8.5.8 INI-free `php -n -v` sandbox acceptance. Inspect built-in modules with `php -n -m` inside fresh, disposable mount and network namespaces, tmpfs chroot, UID/GID 65534, and no-new-privileges. Exclude production INI, dynamic extensions (including IMAP), Barama source, MySQL sockets, customer data, and the updater.

## Pinned host PHP
- Binary: `/www/server/php/85/bin/php`
- SHA-256: `185d97290bc77e23ce77b0a655bea005ccbf8863285b62c8d2c9293105d7712c`
- Previous PHP core sandbox marker: `BARAMA_PHP_CORE_INI_FREE_SANDBOX_PROBE=PASS`
- This follow-up module inventory has NOT yet been run on the aaPanel server.

## aaPanel kit
Artifact: `Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.0.zip`
SHA-256: `a8df66d886ad3cab58790cff36a94beb15402d25cd60b6adf7e4937a6b2bd060`

Execute only after verifying the ZIP:
```bash
cd /root
echo 'a8df66d886ad3cab58790cff36a94beb15402d25cd60b6adf7e4937a6b2bd060  Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.0.zip' | sha256sum -c -
unzip -oq Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.0.zip
cd Barama-PHP-INI-Free-Module-Inventory-aaPanel-v1.0.0
bash run-on-aapanel.sh --check
# Only if the read-only preflight passes, opt in to the confined module inventory:
bash run-on-aapanel.sh --probe PROBE_INI_FREE_BUILTIN_MODULES_ONLY
```

The self-contained ZIP is delivered in the ChatGPT conversation, not stored in the Git repository.

## Release gate
This is a **diagnostic tool only**. It makes no GitHub production code changes. Barama application bootstrap, signed update installation, actual rollback, and CodeCanyon FINAL readiness remain **NOT TESTED / ON HOLD**. Do not merge this branch as an updater release.
