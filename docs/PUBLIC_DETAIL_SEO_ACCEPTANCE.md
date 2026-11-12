# Public detail SEO metadata — Laravel development milestone

This additive development milestone provides route-specific meta descriptions, absolute canonical links, Open Graph/Twitter summary metadata and JSON-LD on PUBLIC approved listing, project, and company detail pages only.

- The existing route service's published-only authorization and visibility checks still decide which records exist publicly.
- No unapproved company, expired listing, private contact record, historical financial details, draft content, or other private source is used.
- The existing frontend layout and media delivery remain unchanged.
- Rich descriptions are stripped of markup, normalized and truncated before being escaped in HTML/JSON.
- JSON-LD is a schema.org WebPage with public Offer, Place or Organization entity; JSON_HEX_* escaping prevents script breakouts.
- No social image field is emitted until media rights and hosted delivery are independently accepted. Sitemap/robots behavior is preserved.
- Full SEO Manager CRUD, localization, publisher image dimensions, canonical override governance and external Search Console acceptance remain future milestones.

This source feature is not a production release. No installation, original PHP site changes, customer data migration, notification, payment or external provider integration is performed.
