# Barama Customer Saved Searches — Laravel

## Purpose

Authenticated, email-verified customers can save the current property browsing filters, revisit searches later, rename them and delete them without affecting any marketplace listing.

## Implemented

- Premium responsive account dashboard, customer sidebar, saved-search cards and public property search toolbar.
- Search supports keyword, sale/rent, property type, ordering and minimum/maximum USD price; all options are validated by the current public search rules.
- Saved searches appear in the Account Dashboard and at `/account/searches`.
- Customer search storage is strictly scoped by `user_id`, with per-user unique names and a limit of 20; writes lock the user record.
- Results are always resolved using the existing public listing query and only show currently approved/published content.
- Invalid filters, negative prices, reversed price ranges, unsafe sorting and unauthorized cross-account mutations are rejected.
- Deleting a search never deletes, changes, or makes private listings publicly visible.
- A customer can revisit an old search after a listing has been unpublished; visibility remains enforced by the marketplace.
- The account dashboard degrades gracefully if an existing customer installation has not applied the additive migration.

## Design and codebase rules

- The UI uses the existing `resources/css/frontend/app.css` and `resources/js/frontend/app.js` pipeline. No per-version CSS files or additional frontend dependencies.
- Nested Laravel controllers under Account/SavedSearches and FormRequest validation under Account.
- No release ZIP generated for this development change; normal GitHub checks verify PHP, Laravel tests and Vite.
- Production deployment requires a **verified backup**, the additive database migration, compiled assets and site acceptance; changes to GitHub Main do not deploy themselves.

## Limitations

The saved search is a private shortcut, **not** an alert subscription. Email notifications for new matching listings, geo/map filters, and multilingual UI are not implemented in this milestone. Advanced search facets require separate domain/query migration.
