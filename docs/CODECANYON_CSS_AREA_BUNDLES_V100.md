# Barama v1.0.0 — CSS Area Bundles (migration ledger)

## Distribution design

The frontend and workspace share `assets/css/app.css` for existing design tokens and base layout. Pages should load one additional canonical, stable **area bundle** where needed. Feature implementations are organized under named section banners inside their bundle, not as permanent, versioned hotfix assets.

Planned bundles: `admin.css`, `frontend.css`, `developer.css`, `account.css`, and `policy-center.css`. Public policy pages have a distinct `policy-center-public.css` to avoid shipping all administrative policy-editor styles to visitors. Independent installer/updater assets must remain self-contained.

### Phase 1 — Policy Center, in this PR

- `policy-center.css` contains 9 sequential feature sections: policy list, categories, editor, feedback, analytics, detail, review/decisions/impact, publishing validation, first-visit guide.
- `policy-center-public.css` contains exactly the latest public policy stylesheet.
- All 7 Policy Center PHP views load one canonical stylesheet rather than 1–6 versioned stylesheets; Policy Center JavaScript has **not** been changed.
- Remove all 29 historical policy-center CSS copies from the customer release. The newest admin base (`v1.0.10`) is an **append-only superset** of `v1.0.6`; no original CSS declarations were discarded.
- All other application asset references remain unchanged in this review wave. Do **not** claim the five-bundle migration is complete or submit this PR as the final CodeCanyon artifact without a new fresh-install verification.

### Required acceptance

1. GitHub Quality / CodeCanyon Staging Readiness and release source/ZIP checks pass against the exact branch.
2. Confirm admin policy list, categories, editor, feedback list/detail/review and public policy index/detail render normally in light and dark mode.
3. Exercise editor Save Draft, blocked Publish, compliant Publish, version history, imported-demo review guide, feedback actions and public legal links.
4. Confirm no CSS 404s, no unexpected selector override, responsive/mobile and keyboard focus.
5. Compare before/after page screenshots on the fresh disposable cPanel site before merging. The branch must not be deployed over the user's working release without backup and confirmation.

### Remaining scope

Admin, frontend, developer and account assets are intentionally **not yet merged into large bundles** because they include conditional styles and overlapping selectors. Evaluate per-page isolation, source order, and third-party dependencies separately; then remove obsolete assets and update source references only after the exact customer build passes regression tests.
