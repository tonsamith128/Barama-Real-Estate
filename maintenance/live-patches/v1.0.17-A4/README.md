# Barama v1.0.17-A4 — Premium Developer / Company application UI

Target: `https://baramarealestate.tonsamith.com/account/developer/apply`

## Actual source change shipped in guarded aaPanel ZIP
- Two-file scope: `application/resources/views/account/index.php` and `assets/css/developer-application-v115.css`.
- Updated the "Become a verified business member" section to a responsive premium two-column hero with an inline decorative Barama-style vector illustration and truthful administrator-review information.
- Replaced plain Developer and Company / Agency labels with large mutually exclusive native radio tiles. Each tile has its own SVG building icon, title, description and checked indicator. Focus and keyboard selection remain accessible.
- Preserved all form fields, CSRF, posting to `/account/company/onboard`, email-verification and pending-application gates, user/role protections, and the existing `developer-application-v115.js` choice/business-type synchronization. No new routes, DB updates or pretend buttons.
- Preserved the previously accepted v1.0.17-A3 Company Access premium design in the same view.

## Captured production source hashes (v1.0.17-A3 baseline)
| File | Expected old SHA-256 | New SHA-256 |
| --- | --- | --- |
| `application/resources/views/account/index.php` | `9b925cb5ab52b5ac39d3d2f7234aadbd702ef7a9a1bb5e554e9145975cfd65d5` | `29c5f3a92aa6f96fa83586d335f447b7325c6504a021771b557acc64ca353001` |
| `assets/css/developer-application-v115.css` | `5e8978fcb81e8555eb04703c1f106230716cc62990c7e8ef460972fae3a29ee2` | `5035d8dfa0210b1f3c2a8f0a3e2efa7962ef881c86a99fc50c9075749626fda1` |

## Guarded package
`Barama-Developer-Application-Premium-v1.0.17A4-Complete-Kit.zip` SHA-256:
`c3fd763926a3c471dd8a9879d674a830ddcf74494a3330c1c28a1898fe2aeb86`

The complete, two-file updated application source, installer and README are distributed in this independently verified aaPanel ZIP. The installer will refuse any unexpected live file changes and makes private source rollback backups.

## QA
- PHP view lint: PASS
- Installer Python syntax: PASS
- ZIP checksum & archive integrity: PASS
- Simulated read-only source check: PASS
- Simulated deployment with post-write SHA checks: PASS
- Repeat installation: PASS
- Rollback: PASS
- Source-conflict refusal: PASS
- Live authenticated browser acceptance: REQUIRED

**Repository note:** GitHub `main` has an old application template, not the manually deployed production v1.0.17-A3 source. This branch is a release record to preserve the exact production source fingerprints. Do not merge or replace production code from old `main` without first reconciling the accepted live source. The actual source changes are in the delivered ZIP, not represented as direct changes to GitHub `main`.
