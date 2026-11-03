# Barama v1.0.16 — Apply for Developer / Company header

Target: \`/account/developer/apply\`. Production baseline: **v1.0.15 live aaPanel patch**.

## Behavior
- If the signed-in user has authorized Developer workspace access, show the real **Open Business Workspace** action at the top right linking to \`/developer\`.
- If the user lacks Developer access, do **not** display an inaccessible action. Keep the actual application form, email verification, and pending review states.
- Remove the redundant **Back to Company Access** button inside the intro card.
- Keep the status message for already-approved members without a duplicate link.
- Use CSS scoped to the new header action. No database, permissions, endpoints, or unrelated page changes.

## Delivery strategy
The current GitHub \`main\` branch contains an earlier application snapshot and has not yet incorporated the manually deployed v1.0.15 live patch. **Do not merge this branch directly into \`main\` before reconciling production v1.0.15.** This folder stores a reviewable incremental source diff against the exact deployed v1.0.15 bytes.

Production aaPanel installer (distributed separately in chat) validates these exact old SHA-256 hashes before update:
- \`application/resources/views/account/index.php\`: \`be16d4e018991a3f133e4a13c8f2b7daa37d543eb932e9696aaa4ec7e2e60813\`
- \`assets/css/developer-application-v115.css\`: \`31d1366a74f7ae4dc1b2ca0144047d6037b0348f8e617c53bf94a9fc9d2bf548\`

Live script path: \`/root/barama-apply-header-safe-upgrade-v116.py\`. The installer performs read-only check, private backup, atomic 2-file update, PHP lint, and rollback.

Expected results:
\`\`\`ini
SOURCE_STATUS=MATCHES_V115
READ_ONLY_CHECK=PASS
DEPLOYMENT=PASS
FILES_UPDATED=2
DATABASE_MODIFIED=NO
OTHER_SITES_MODIFIED=NO
\`\`\`
