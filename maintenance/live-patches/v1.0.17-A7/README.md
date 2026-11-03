# Barama v1.0.17-A7 — Admin Location Provider selection

## Objective

Keep existing **Selected Countries / Global** scope settings independent of the new global **Location Provider** setting, configurable only by authorized administrators.

### Functional choices
- **Simple Location**: the existing Barama country/location cascade and canonical `location_id` relationship; selected by default; no API access or settings file needed.
- **Mapbox Location**: optional Geocoding v6 address lookup, with a real saved access token, required successful connection test before activation, fixed server-side Mapbox HTTPS endpoint, query-rate limits, and authenticated search. The UI never exposes the API token. Provider selection affects the location cascade forms across the application.

### Security and persistence
- Provider config stored encrypted with AES-256-GCM and installation `APP_KEY` inside protected `application/storage/app/location-provider/provider.enc`.
- Admin role and `location.manage` enforced for settings read, save, token update, connection test, and activation; CSRF remains in existing POST settings route.
- Mapbox search available only to signed-in accounts; rate-limited to 8 queries per user per minute and 100 global per minute.
- Results are temporary (**permanent=false**) and **never persisted** into Barama's 5,056 current `locations` records or property addresses. The user selects an existing canonical administrative area to save an entity. This is deliberate to respect Mapbox storage terms. Permanent address import is not claimed as implemented.
- No new database migration or modification during installation; saved provider setting is a private runtime configuration created only by Admin actions.

### Source files updated / created
1. `application/app/Http/Controllers/LocationProviderController.php` (new)
2. `application/app/Http/Controllers/WorkspaceSettingsController.php` (modified)
3. `application/app/Repositories/LocationProviderRepository.php` (new)
4. `application/app/Services/LocationProviderService.php` (new)
5. `application/app/Services/MapboxLocationClient.php` (new)
6. `application/resources/views/workspace/location-cascade.php` (modified)
7. `application/resources/views/workspace/settings-locations.php` (modified)
8. `application/resources/views/workspace/settings.php` (modified)
9. `application/routes/web.php` (modified)
10. `assets/css/location-provider-v117a7.css` (new)
11. `assets/js/location-provider-v117a7.js` (new)

## aaPanel kit

`Barama-Location-Provider-v1.0.17A7-Complete-Kit.zip`

SHA-256: `51b75774113f13b828e6ac39c6175492c0c7f92397f8e225239141ddda43e140`.

Exact-source guarded `--check` / `--deploy` / `--rollback --backup` installer. The latest accepted production baseline includes manually deployed v1.0.17-A6 Catalog improvements and v1.0.17-A1 authentication routes. This installer does not change the A6 Catalog workspace view.

QA: 9 PHP lint PASS; JS syntax PASS; ZIP checksum PASS; simulated check/deploy/idempotency/rollback/conflict rejection PASS; AES-256-GCM encrypted readback/tamper/fingerprint tests PASS. Actual authenticated and Mapbox-token live-browser QA PENDING.

### Repository baseline warning

The `main` source remains behind the manually deployed production site. This draft branch is a **release record**, not a backported mergeable source replacement. The complete code is distributed in the guarded ZIP. Reconcile accepted production source into GitHub before merging code from older `main`.

Mapbox terms: https://docs.mapbox.com/api/search/geocoding/ and https://docs.mapbox.com/help/dive-deeper/understand-temporary-vs-permanent-geocoding/ .
