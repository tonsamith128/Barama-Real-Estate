# Customer Marketplace Engagement

Introduced in v0.7.0-dev.

## Customer account routes

Authenticated customers use one canonical account workspace:

- /account
- /account/saved
- /account/compare
- /account/inquiries
- /account/viewings
- /account/alerts
- /account/recent

Every mutation requires authentication, account.access, and CSRF validation.

## Saved properties

A saved item belongs to one user and one listing.

Only currently public listings can be added. Account reads re-check listing, property, and company publication state before exposing marketplace data.

## Comparison

Customers can compare up to four public listings at once.

The comparison limit is enforced in CustomerEngagementService rather than only in the browser.

Comparison ordering is persisted and rendered deterministically.

## Recently viewed

Opening a public property while signed in upserts a per-user listing view:

- first_viewed_at
- last_viewed_at
- view_count

Guest browsing does not create customer-history records.

## Inquiry history

The existing inquiries.user_id relationship is reused.

Signed-in marketplace inquiries already pass the authenticated user ID into InquiryService, so the customer account reads those canonical inquiry records instead of introducing a second message table.

## Viewing requests

A signed-in customer can request a viewing for a currently public listing with:

- preferred date
- preferred time window
- optional alternate date/time
- optional message

Only one active viewing request per customer/listing is allowed by the service.

Customer-cancellable states are:

- pending
- confirmed
- rescheduled

Cancellation cannot mutate another customer's request.

The schema also includes scheduled_for and company_note so company-side scheduling can extend this foundation without replacing it.

## Saved searches and alert preferences

Saved searches store normalized marketplace criteria:

- keywords
- sale/rent
- property type
- location
- minimum bedrooms
- minimum/maximum price
- currency
- daily/weekly preference
- enabled state

The account can open the current matching marketplace query directly.

This phase intentionally does not claim automatic background email delivery. The saved alert preference is production persistence for a later delivery worker once final mail and cron configuration are available.

## Asset policy

Customer engagement continues to use only:

- assets/css/app.css
- assets/js/app.js

No page-specific first-party CSS or JavaScript bundles are introduced.
