# Media Library / Listing Gallery — Laravel module acceptance

**Status:** Development QA module for the clean Laravel 13 application. Not a production CodeCanyon release.

## Responsibility boundaries

- `App/Services/Media/ListingGalleryManager.php`: draft-only tenant-scoped mutations, image validation, private deduplicated storage, attachment ordering, transactional audit.
- `App/Services/Media/PrivateImageLocator.php`: read private files only with a database path whitelisted to `private/demo` or `private/media`; validates digest, MIME, existence and symlinks. Caller must perform access control first.
- `Developer/Media/ListingGalleryController.php`: CSRF, upload bounds, approved company owner/manager, draft listing preview and removal.
- `Admin/Media/MediaLibraryController.php`: super-admin-only library, private previews.
- `Frontend/Media/PublicDemoMediaController.php`: legacy demo + newly uploaded file delivery **only** through the existing published-entity policy.
- `MediaAsset` and `MediaAttachment` models retained. No extra media tables or changed migrations.

## Security contracts

1. JPEG/PNG/WebP only, based on file content and real detected MIME (not user-supplied extension).
2. Upload ≤12 MiB and ≤24 MP, with explicit 8000×8000 dimension bounds.
3. Store hashed filenames outside public/ via the private local Laravel filesystem disk. No symlink or public storage symlink dependency.
4. Only verified active owner/manager members of the approved company may upload to that company's draft listing. Published/pending property media cannot be modified directly.
5. 20 images maximum per listing. Identical file repeated is an idempotent attach.
6. When a file already exists, reuse the single content-addressed media asset; detaching a listing never deletes a shared original.
7. Draft images are available only via authenticated developer/company preview or super-admin inventory. Public image routes return 404 until the property, company and offer are approved/published.
8. Changed content and publication remain admin-moderated. Every successful upload/detach has an audit entry.
9. No arbitrary filesystem paths or content-type headers can be provided by the browser.

## Manual end-to-end test, isolated staging

1. Log in as the installation super administrator and create an approved company assigned to a verified developer.
2. Log in as that developer. Create a draft listing from Developer > Properties.
3. Upload one PNG or JPEG with alternate text and confirm a private thumbnail appears in the Developer draft gallery.
4. In another unauthenticated browser window, the direct media URL must return 404; the unpublished property must not show in public search.
5. Re-upload the exact image and verify it does not create an extra attachment.
6. Try an executable renamed `photo.png`, invalid MIME, a different company's listing, a read-only membership, and a 13 MB image. All must fail without visible data loss.
7. Remove an image and verify its listing attachment is removed but shared originals are retained.
8. Upload again and submit the listing for administrator review. Admin approves via Marketplace Moderation.
9. Verify the public property card and detail gallery now show the image. Changing publication back to draft must revoke public image access.
10. Open Admin > Media Library and verify it lists image dimensions, MIME and reference counts, with private preview accessible only to super-admin.
11. Confirm CSS/JS is loaded at `public/build/assets` and that the generated ZIP's Vite manifest references those exact files.

## Not yet migrated

Advanced Media Library collections, bulk multi-file uploads, resizing/thumbnail processing, responsive variants, remote object storage, virus-scanner integration, rights confirmation and advanced image moderation. These remain separate product milestones; this release does NOT claim original Barama feature parity.
