# Public Marketplace UI

Introduced in v0.6.0-dev.

## Canonical public routes

- /
- /properties
- /properties/{listing-slug}
- /projects
- /projects/{project-slug}
- /company
- /company/{company-slug}
- /media/{media-uuid}

## Publication boundaries

Marketplace queries expose only records that meet the domain lifecycle requirements.

Listings require:

- listing status published
- listing published_at set
- listing not expired or deleted
- underlying property approved and not deleted
- owning company approved, published, and not deleted

Projects require a published project and an approved/published company.

Companies require approved status and published_at.

The media route independently re-checks the publication boundary of the attached entity before reading a private upload. Knowing a media UUID does not bypass entity publication state.

## Discovery

Property discovery supports:

- keyword search
- sale/rent offer
- property type
- location
- bedrooms
- minimum/maximum price
- newest/oldest/price sorting
- server-side pagination

Project discovery supports keyword, type, completion stage, and location filters.

Company discovery supports keyword, company type, and location filters.

All SQL filters use prepared parameters. Sort clauses are selected only from internal allow-listed values.

## Inquiry capture

Property, project, and company detail pages reuse the existing InquiryService and CSRF middleware. The form is progressively enhanced by the canonical assets/js/app.js and retains the existing honeypot and rate-limiting protections.

## Asset policy

This phase preserves exactly one first-party stylesheet and one first-party JavaScript file:

- assets/css/app.css
- assets/js/app.js
