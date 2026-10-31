# Homepage Builder ↔ Media Library Integration

## What is implemented

The administrator can upload a JPEG/PNG/WebP from **Dashboard → Media Library**, edit its alt text and declared rights details, and then choose it in **Dashboard → Homepage Builder** using an accessible visual thumbnail picker.

- Admin uploads are validated by image bytes and dimensions, restricted to 12 MB and 24 megapixels, stored on the private local disk by SHA-256, and deduplicated.
- New uploads are **unreviewed** by default. Only Media Library assets declared **owned** or **licensed** with a nonempty rights holder are selectable. These declarations are *not* independent evidence of image rights; marketplace legal review remains necessary.
- Homepage Builder includes optional hero image and independent images for Text, Feature and CTA sections, with responsive left/right image placement.
- The picker searches the available library and displays up to 36 recent matches, while **always preserving selectable images already attached to the draft**, including older images and those absent from the current search. If an attached image loses rights eligibility, the warning remains and the image is not silently reauthorized. Existing text-only sections and drafts stay compatible.
- All image choices use existing `MediaAsset` IDs inside the existing `site_appearances` JSON draft/published snapshot. **No new database table or filesystem copy is required.**
- Public image URL `/media/homepage/{asset}` serves a private original *only* if it is referenced by the currently published hero or an enabled published section **and** currently has permitted rights metadata.
- A draft image is never publicly accessible. Removing its published reference or changing rights to restricted/unreviewed immediately revokes the public route; private admin preview continues to be authenticated.
- Invalid image IDs, unreviewed rights, arbitrary remote URLs, disallowed positions and cross-role actions are rejected. Images remain private when the associated section is hidden.
- Safe server-side Blade escaping, bounded image inventories, lazy loading for content images, and responsive image layout use existing consolidated `resources/css/admin/app.css` and `resources/css/frontend/app.css`.
- Assets are never deleted when removed from a homepage section, so property listing galleries and other consumers remain intact.

## Administrator workflow

1. Upload to `/dashboard/media`; enter accessible alt text.
2. Update rights status, rights holder, and license/evidence reference (as applicable).
3. Open `/dashboard/appearance/homepage`, choose an image for the hero or a section and select the section image position.
4. Save. The editor's private preview is visible to administrators; the public homepage and public image route remain unchanged.
5. Use **Publish website appearance** when ready. Verify the homepage and mobile layout.
6. Removing an image from the draft does not unpublish it until the next Publish action. Rights revocation immediately stops public image delivery.

## Limitations / later development

This milestone does not verify copyright ownership or license validity. An independently approved image-rights workflow, image variants/WebP conversion, cropping, focal points, drag-and-drop visual canvas, custom page layouts, global asset folders and commercial demo-media distribution rights remain pending.

**Development-first release discipline:** do not create a QA ZIP for each commit. GitHub tests cover core behavior; full cPanel installer and security acceptance remain mandatory before the CodeCanyon commercial release. GitHub updates do not deploy to `codecanyon.salabandet.com`.
