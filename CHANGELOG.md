# Changelog

All customer-facing changes to Barama Real Estate will be recorded in this file.

Development-only 0.9.x milestones are intentionally not presented as separate CodeCanyon customer releases. Git history remains the source of truth for pre-release engineering changes.

## 0.9.25 — 2026-10-23 (Live Demo source release)

- Promote the GitHub Main Live Demo source to a consistent pre-1.0 release version.
- Include the verified Auto Updater code merged via PR #126 and its PHP/browser QA acceptance.
- Align deployed source `VERSION` and installed runtime metadata as one guarded deployment, without changing the database schema, runtime secrets, or uploaded media.
- Portable signed update installation still requires host-appropriate private vault and controlled write permissions; this version promotion alone does not certify that `Update Now` can install a future release.


## Unreleased — v1.0.0

Initial CodeCanyon release preparation.

### Marketplace
- property sale/rent marketplace
- project/development discovery
- public company/developer directory
- responsive property/project/company detail experiences
- save, compare, viewing request, inquiry, and saved-search customer workflows

### Accounts
- secure customer registration, login, logout, email verification, and password reset
- customer profile, privacy, security, notifications, saved items, comparisons, alerts, recent views, and company onboarding
- company/vendor workspace with company membership boundaries
- administrator dashboard with moderation, catalogs, user controls, and security audit visibility

### Real estate management
- locations and amenities
- companies/developers and team membership
- projects
- properties
- sale/rent listings
- private media management
- inquiries and leads
- controlled lifecycle/status transitions

### Installation and operations
- five-step one-click installer
- Ton Samith direct and CodeCanyon/Envato license verification methods
- MySQL/MariaDB installation and ordered migrations
- first-administrator provisioning
- PHP-mail defaults during installation with optional authenticated SMTP configuration after installation
- secure browser updater with administrator re-authentication and pending-migration execution
- notification/outbox command for scheduled hosting jobs

### Release quality
- Light/Dark Mode and responsive layouts
- English, Khmer, and Chinese localization foundation
- Apache/Nginx routing compatibility preflight
- exact-package browser/accessibility and upgrade QA
- third-party notices and CodeCanyon package compliance gates
- customer HTML help documentation

The final v1.0.0 entry will receive its release date only after all live-server, human visual/content, online-documentation, and final package acceptance gates are completed.
