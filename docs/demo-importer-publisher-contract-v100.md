# Barama Live Demo Importer v1.0.0 — Publisher + Customer Contract

**Integration status:** customer-side implementation on draft PR #130; **not live**. The publisher Demo Content integration at `https://tonsamith.com/dashboard/demo-content` has not yet been verified to support Barama. No complete Barama demo archive has been published or retrieved. Do not mark the end-to-end feature complete or deploy to customer production on the strength of a UI screenshot.

## Target workflow

Customer menu: **System → Demo Importer**, route `/dashboard/system/demo-importer`.

- **Manual Import:** commercial customer downloads the Barama demo ZIP from the authenticated publisher Demo Content page, uploads the ZIP, sees the exact signed package record/media/configuration counts, and explicitly installs after Change Confirmation.
- **Auto Import:** the Barama installation sends a bound license request directly to the publisher; the publisher checks the commercial license and provides the same exact signed ZIP. It is inspected/staged identically. User still explicitly approves installation; *auto* means automated retrieval, **not automatic destructive database writes**.
- Both modes reuse one validator and one installer. No unverified SQL, PHP, code update ZIPs, site scraping or unauthorized external image URLs.

## Publisher API integration required (not yet deployed/verified)

`POST https://tonsamith.com/license/v2/demo/package`

Authenticated body:
```json
{
  "activation_token": "<current installation token, never log>",
  "installation_id": "<32 hex chars>",
  "domain": "<licensed canonical domain>",
  "product_slug": "barama-real-estate",
  "package": "barama-live-demo",
  "mode": "download"
}
```

Successful entitlement: HTTP 200 with `Content-Type: application/zip` containing the exact current signed ZIP, max 64 MiB, no redirect. Denied/missing package: non-200 (no payload interpreted as an installable ZIP). HTTPS certificate/hostname verified.

The existing activation-token validation response must explicitly include `demo_content_entitled: true` **only** for commercial (regular/extended/eligible issued) customers. This entitlement MUST be independently checked server-side by TonSamith.com for the exact domain, installation ID, product and activation token. Tester licenses, expired/suspended/revoked licenses and arbitrary uploads must fail closed. Do not rely on a client-supplied `is_commercial` flag.

## ZIP artifact

Archive entries:
- `envelope.json`
- `content.json`
- `assets/<sha256>.jpg|png|webp`, each complete image byte-for-byte

Manifest:
```json
{
  "manifest": {
    "schema": "tonsamith-barama-demo-v1",
    "publisher": "tonsamith.com",
    "product_slug": "barama-real-estate",
    "package_id": "barama-live-demo-v1",
    "key_id": "<offline-pinned publisher key id>",
    "minimum_version": "0.9.24",
    "expires_at": "2027-01-01T00:00:00Z",
    "files": {
      "content.json": "<sha256>",
      "assets/<sha256>.webp": "<sha256>"
    }
  },
  "signature": {
    "algorithm": "Ed25519",
    "key_id": "<same key id>",
    "value": "<base64 detached signature>"
  }
}
```

The `files` object must enumerate **exactly** the content and media entries other than `envelope.json`. The publisher signs `TONSAMITH-BARAMA-DEMO-V1\n` concatenated with the manifest JSON using top-level sorted keys (`JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`). The existing independently pinned Publisher V2 Ed25519 catalog supplies a public verification key. The signature domain is different from signed code releases.

`content.json`:
```json
{
  "schema": "barama-demo-content-v1",
  "components": ["Homepage", "Navigation", "Footer", "Theme", "Pages", "Marketplace", "Images"],
  "settings": {
    "cms.site_content": {"homepage": {}},
    "cms.footer_content": {},
    "appearance.frontend": {}
  },
  "tables": {
    "locations": [],
    "amenities": [],
    "companies": [],
    "projects": [],
    "properties": [],
    "listings": [],
    "cms_pages": [],
    "media_assets": [],
    "project_amenities": [],
    "property_amenities": [],
    "media_attachments": []
  }
}
```

The publisher exporter must generate this from the **real published Barama demo data**, ensuring:
- no production operator users, auth sessions, purchase codes, passwords, API keys, payment credentials or inquiry/leads/customer histories;
- public-site CMS and marketplace records only, complete references, coherent IDs, and safe data for the requested demo license;
- all related local/R2 media bytes are fetched into the package (no broken remote references);
- each media row uses `disk: private`, `path: demo/<sha256>.<extension>` and matching `checksum_sha256`; the corresponding ZIP entry is `assets/<sha256>.<extension>`;
- the site's appearance/navigation/footer settings reference the package media IDs/paths and use recipient-local URLs rather than live demo hostnames;
- publisher attestations include the Barama app version/schema compatibility. Frontend PHP/JS templates come from the *customer application's installed software*, not from this content ZIP.

## Safety of the current customer importer

- Only administrator/super-administrator access; CSRF + Change Confirmation security gate.
- ZIP header, entry limits, exact signed files, Ed25519 signature, SHA-256, package expiry and allowed record components.
- Manual staging never imports. Auto retrieval never installs automatically.
- Install demands fresh content tables (zero rows across package's tables), online commercial entitlement, typed `IMPORT LIVE DEMO`, and successful pre-import private database backup.
- DB records inserted transactionally without customers, auth or finance tables. Uploaded PHP/SQL/executable ZIP paths are rejected. Media files have per-file digest and cleanup on DB failure.
- If an installed customer website already has content (including seeded geographic records), installation **stops rather than erasing or merging it**. Designing safe semantic merges and ID remaps is separate acceptance work.
- Media are local private-disk copies; customer Cloudflare R2 reconciliation and frontend media delivery must be tested on representative hosting.
- Current importer does not promise atomic rollback across database and *external* object storage or a full media restore rehearsal.

## Required release gates

1. Implement and publish Barama demo package in TonSamith.com Demo Content, with public key/trust ceremony. **Not performed**.
2. Confirm commercial-license entitlement response and authenticated download endpoint on the **actual** publisher. **Not verified**.
3. Generate an actual packaged export from the live Barama demo and verify every linked image and CMS configuration. **Not performed**.
4. Test manual and auto retrieval, exact-signature negative fixtures, fresh-site import, navigation, homepage, footer, all public pages, media, listings, and branded design in isolated staging. **Not performed**.
5. Test partial failure, repeated import, failed entitlement, wrong-domain license, backups, and recovery. **Not performed**.
6. After complete acceptance, build a signed, scoped, version-appropriate aaPanel handoff for the installed v0.9.24 Production site; do not overwrite with Main v0.9.25. **Not prepared**.

Do not merge or deploy PR #130 until the listed publisher and staging gates are addressed. Demo Importer cannot be honestly described as fully operational solely because the customer-side code compiles.
