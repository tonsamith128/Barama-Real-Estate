# Policy Center — Laravel Publishing Acceptance

This module allows administrators to author and publish legal-policy pages without a compliance-percentage blocking workflow.

## Architecture
- `app/Http/Controllers/Admin/Policy/LegalPolicyController.php`: super-admin-only create/edit/publish/unpublish, validation and transactionally persisted audit events.
- `app/Services/Policy/LegalHtmlSanitizer.php`: allowlist sanitizer that removes active HTML, scripting and style/event attributes; permits strictly validated HTTPS/HTTP hyperlinks.
- `app/Http/Controllers/Frontend/Legal/LegalPageController.php`: publish-only public pages; sanitized HTML at render time, including imported historical templates.
- `resources/views/admin/policies/`: draft editing and publication controls; no arbitrary compliance threshold.
- `resources/views/frontend/legal/`: accessible list and legal page display; status-gated.
- Existing `legal_policies` table reused. No destructive schema changes.

## QA checklist
1. Super administrator visits `/dashboard/policies`, creates Terms of Service, saves draft, and sees it as unpublished.
2. Public `/legal/terms-of-service` returns 404 while draft.
3. Super administrator clicks Publish. Public page appears and is linked from `/legal`.
4. Editing published content returns page to Draft until Publish is clicked again.
5. Unpublish immediately revokes public access. No compliance score or administrator-only hidden barrier is involved.
6. Regular accounts cannot create, edit, publish or unpublish; guest users cannot access the management center.
7. HTML containing `script`, `svg`, `iframe`, `onclick`, `style` or `javascript:` cannot appear as executable output on public pages.
8. Original demo policies imported as drafts are sanitized automatically on explicit publication.
9. File names remain version-free; both frontend/admin CSS consolidate the new module into existing bundles.
10. No changes to installed customer data or production domains.

## Remaining work
Full rich-text editor with safe structured widgets, translated legal pages, scheduled publication, revision diffs, legal-review history, subscriber acknowledgement receipts, notifications and data retention settings. Those are separate explicit release gates and are not claimed to be complete.
