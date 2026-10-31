# Laravel 13 Source Policy

The canonical application is Laravel 13. Controllers, routes, middleware,
Eloquent, migrations and services belong to the Laravel application.

Installer/ is a narrowly scoped setup bootstrap for an empty hosting account.
It may prepare .env and the first administrator before Laravel is installed,
but must not provide a second marketplace runtime.

Source standards:
- Only Laravel 13 application routing, authentication and authorization.
- Two grouped first-party CSS files, and frontend/admin JS entry points.
- Descriptive source filenames without embedded release numbers.
- No duplicate framework, root application router or historical asset bundle.
- A fresh-install ZIP must never overwrite an existing customer installation.
- Execute scripts/verify-laravel-only.php against source and extracted ZIP.
- Preserve requirements for safe upgrades from existing customer databases.

Branch governance:
Historical branches must be reviewed against open pull requests, unique work,
tags and existing deployments before retirement. Do not merge branches with a
retired runtime into Main. Deleting Git branches does not erase Git history.
Force-repointing historical branches is not a safe substitute for deleting
them.

Read-only inventory from a fully fetched local clone:

    git fetch origin '+refs/heads/*:refs/remotes/origin/*'
    bash scripts/report-branch-runtime.sh

Release approval requires: clean CI, completed feature-parity register,
MySQL fresh install and non-destructive upgrade tests, backup/recovery,
independent aaPanel and cPanel checks, provider acceptance, accessibility,
security, performance and final customer ZIP verification.
