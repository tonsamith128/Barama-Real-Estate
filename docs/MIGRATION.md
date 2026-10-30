# Migration status (foundation)

Source: main branch Barama v1.0.0, custom Pure PHP framework.
Target: this separate Laravel 13 branch. NO existing production changes.

| Module | Target folder | Status |
|---|---|---|
| Framework/router | bootstrap, routes | Foundation |
| Frontend/Home | app/Modules/Frontend/Home | Static foundation only |
| Auth/Login/Register/Password/Email | app/Modules/Auth | Not migrated |
| Account | app/Modules/Account | Not migrated |
| Developer Workspace | app/Modules/DeveloperWorkspace | Not migrated |
| Admin/Dashboard/Developer | app/Modules/Admin | Not migrated |
| Admin/Appearance/Header/Footer | app/Modules/Admin/Appearance | Not migrated |
| Admin/Finance/MediaLibrary/PolicyCenter | app/Modules/Admin | Not migrated |
| MySQL schema/data | database/migrations | Not migrated |
| Installer/License/Updater/DemoImporter | app/Modules/System | Not migrated |

Next technical milestones:
1. Inventory legacy routes, tables, keys and data model; preserve baseline.
2. Implement native Laravel authentication with throttling, verified email, CSRF and tests.
3. Migrate company ownership/permissions and real estate domain rules.
4. Migrate marketplace and each workspace with regression tests.
5. Migrate installer, licensing, signed updater, backups and demo importer.
6. Build exact customer ZIP and test MySQL, aaPanel/cPanel, browsers and rollback.

