# Barama Real Estate

Barama Real Estate is a PHP, Bootstrap, and JavaScript real-estate marketplace and property-management platform by Ton Samith.

## Release target

- Current development line: `0.x`
- CodeCanyon target: `v1.0.0`
- Author: Ton Samith
- Author URL: https://tonsamith.com
- Demo target: https://baramarealestate.tonsamith.com
- Marketplace: CodeCanyon
- Online Documentation: https://docs.tonsamith.com/docs?=228711-barama-real-estate-property-marketplace-developer-management-php-script
- Support: https://tonsamith.com/contact

## Account areas

- Admin Dashboard: `/dashboard`
- User Account: `/account`
- Public Developer / Company: `/company`
- Developer Account: `/developer`

## Architecture rules

This repository intentionally starts with a small canonical foundation.

- One application front controller: `index.php`
- One custom stylesheet: `assets/css/app.css`
- One custom JavaScript entry point: `assets/js/app.js`
- No duplicate versions of application files
- No abandoned legacy implementations
- No generated build artifacts committed to source
- Controllers contain request orchestration, not database/query logic
- Core framework primitives live in `application/app/Core`
- Public, user, developer, company, and admin concerns stay route/controller separated
- Database changes will be additive, ordered migrations
- Production errors must never expose stack traces to visitors
- The customer-facing release must not require Node.js

## Runtime requirement

- PHP 8.2+
- PDO with a MySQL/MariaDB driver
- Apache or Nginx
- Bootstrap 5 is currently loaded from the official CDN during the foundation phase

## Authentication foundation

The current development line includes ordered database migrations, customer registration, secure login/logout, password reset links, login throttling, role/permission records, CSRF protection, and middleware-enforced access boundaries for \`/account\`, \`/developer\`, and \`/dashboard\`.

The installer will create the first administrator account from customer-supplied credentials. Administrator credentials are never hard-coded in the repository.

The final CodeCanyon package will include installer, upgrade, licensing, documentation, and release QA layers before \`v1.0.0\`.
