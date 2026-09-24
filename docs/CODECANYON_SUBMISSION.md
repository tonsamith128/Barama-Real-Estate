# CodeCanyon Submission Readiness

This checklist tracks repository-controlled preparation for Barama Real Estate before the final marketplace submission.

## Automated package requirements

The exact customer ZIP must:

- contain the complete runtime PHP source
- contain `documentation/index.html`
- contain one customer-facing `CHANGELOG.md` without duplicate source-version trees
- contain `LICENSE.md`, `THIRD_PARTY_NOTICES.md`, and `CUSTOMER_PACKAGE.md`
- keep one canonical first-party stylesheet and one canonical first-party JavaScript file
- exclude internal GitHub workflows, development docs, test fixtures, browser automation packages, logs, caches, dumps, backups, runtime license state, and test credentials
- contain no demo marketplace inventory or administrator credentials
- contain no runtime `eval()`
- contain no unsafe PHP short tags
- contain no inline CSS style attributes in shipped application/help markup
- contain no inline JavaScript event-handler attributes in shipped application/help markup
- contain no runtime debug dumps such as `var_dump()` or `print_r()`

## Customer help file

The packaged HTML documentation must cover:

- minimum PHP/database/extensions
- one-click five-step installer
- licensing methods
- account/workspace URLs
- installer PHP-mail defaults and post-install SMTP configuration
- scheduled notification/outbox jobs
- upgrade workflow and backup requirement
- third-party asset credits
- customer version history / changelog reference
- online documentation and support links

## Final manual gates

Do not label the item v1.0.0-ready until the remaining human/live gates in `docs/RELEASE_GATES.md` are completed, including:

- live License Manager activation through the installer
- live Apache/Nginx installer acceptance
- live production mail provider delivery
- human desktop/tablet/mobile and Light/Dark visual review
- human English/Khmer/Chinese content review
- Safari/iOS and Edge/Chrome acceptance
- online documentation parity review
- final CodeCanyon upload-form/package review

## Envato alignment

The checklist is designed around current Envato code-item expectations for organized source, secure/well-abstracted PHP, clear documentation, asset licensing/credits, and avoiding common rejection patterns.

This file is an internal release checklist and is not included in the customer ZIP.
