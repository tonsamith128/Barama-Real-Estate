# Barama Company Access premium card — v1.0.17-A3

**Production URL:** `/account/company`

## Scope
This release replaces the lower “Become a Developer or Company” promotional section with a visually refined, responsive, centered premium card. The CTA remains a genuine anchor to the existing `/account/developer/apply` route. No demo actions, invented accounts, API changes, or database migrations.

### Preserved conditions
The promotion is displayed inside the existing:
```php
<?php elseif ($canRequestCompany && !$hasWorkspaceAccess): ?>
```
branch following the email-verification gate. The company table, invitation actions, and approved-business workspace access remain unchanged.

### Exact live-source baseline
The GitHub default branch `main` does **not** contain the current production Company Access v1.0.14 and Apply v1.0.16 source. Do **not** replace that live view with the older GitHub version or merge this documentation-only branch as a production source rollout.

The guarded aaPanel package supplies **both complete source files** and verifies these SHA-256 values before applying:
| File | Old SHA-256 | New SHA-256 |
| --- | --- | --- |
| `application/resources/views/account/index.php` | `67f626adbd97494f06a443255191f8c9975d918c2de54a58a7c2081fece821a4` | `9b925cb5ab52b5ac39d3d2f7234aadbd702ef7a9a1bb5e554e9145975cfd65d5` |
| `assets/css/account-company-access-v114.css` | `815a65b8a06dbe8eb2abd6ae8c5037bab4a4959fb235b5e7692fb82f955d4ee6` | `b21b7a3e643bfe77f439d69bd9a21075aef3fd5d201ba5a1f7babb5de33dedb8` |

### Release artifact
`Barama-Company-Access-Premium-Card-v1.0.17A3-Complete-Kit.zip`

SHA-256: `9cc9e666e47eb70825964ad31b70fee0cd1a2ed4d8ee1292961227eacf4103c4`

The ZIP is distributed to the site owner through ChatGPT, not committed here. It contains a source-matched installer, payload files, checksum manifest, README, verified source rollback, and refusal of unrelated file changes.

### QA gates
- PHP 8.4 template syntax: PASS
- ZIP integrity and per-file SHA-256: PASS
- Simulated --check: PASS
- Simulated install and recheck: PASS
- Repeat install: PASS
- Source rollback: PASS
- Conflict refusal: PASS
- Authenticated live-browser review: REQUIRED

Before any merge, reconcile GitHub `main` with the accepted production source; this branch is a **release record only**, not an actionable replacement for the application's historical templates.
