# Barama signed Demo Content — version-independent schema contract (v1.0.0)

**Date:** 28 October 2026  
**Purpose:** Allow unchanged signed demo snapshots to be installed on newer Barama application releases **without rebuilding or resigning them solely because the application's display version changed**.

## Publisher immutability

The known published Demo Content `v0.9.26` release `DCP-20261028-BB4A3B29` (SHA-256 `b577010057631afef2364ed51456f1ab1849ba41918548169109e103b482662e`) was accepted on the v0.9.25 application. Its signed manifest includes an earlier `compatible_app_versions` array. **Do not edit that ZIP or its signed publisher metadata.** The detached Ed25519 signature remains anchored to the original content bytes.

## Compatibility decision

The current importer accepts the same authenticated snapshot on **v1.0.0 and later releases** only when all these are satisfied:

1. A current active, domain/installation-bound licensed publisher entitlement and exact published catalog identity are verified.
2. The archive's original SHA-256 matches its published digest; detached Ed25519 signature verifies against the independently pinned publisher public-key fingerprint. No unsigned or modified ZIP is accepted.
3. The signed native archive uses manifest `schema_version = 1`, content `schema = "barama-demo-content-v1"`, and strictly validated table, setting, reference, image and policy structures.
4. The complete customer-release floor remains `demo version >= 0.9.26`, exactly **20 public source policies**, at least one content record and one verified image.
5. A read-only destination database-schema preflight checks **all signed table names and signed column names** against the installed database before enabling or attempting installation. Unknown schema versions, renamed/removed columns and absent tables block installation.
6. The real installer rechecks the current publisher catalog and signing identity, requires explicit customer confirmation, rejects populated content tables, takes a verified backup before any data writes, uses a transaction, and cleans up created files on failure. The signed policies are installed as recipient-private unapproved templates, never published terms.

The old `compatible_app_versions` field is still validated for **syntax** when present, but is no longer an exact-match authorization list. New content publishers may omit it. New signed format changes are **never automatically accepted**. Existing customer data and deployed sites are not modified by this source-only PR.

## Required release evidence

- Pure gate fixture: original v0.9.26 proof with `["0.9.25"]` passes policy for application `1.0.0` and a later label; invalid schema, unsigned, invalid legacy metadata or fewer policies still fail.
- Native signed-archive fixture: content schema + table-column proof is extracted, and the new schema-only manifest without version labels is accepted.
- Isolated MySQL fixture: test application reports `1.0.0`, native signed demo still carries original legacy app label, schema preflight passes for matching columns and denies a signature-valid archive with unknown destination column. Existing rollback tests remain mandatory.
- Full exact customer-package Quality / CodeCanyon Staging Readiness gates pass on the new source commit.
- On a disposable activated **v1.0.0** cPanel site (e.g. `codecanyon.salabandet.com`), re-download the **unchanged** published `v0.9.26` package, verify eligibility, take the pre-import backup, explicitly install, and confirm 5,255 content records, 53 signed images and 20 recipient-private policy drafts. Do **not** repeat on an already populated customer installation.

## Limitations

Schema preflight is necessary but not a claim that every later database migration is semantically backward compatible. New constraints, data types or renamed settings may still make a demo incompatible. Transactional insert validation and backup/rollback remain essential. A substantial schema change should introduce a new supported demo-content schema and a fresh reviewed publisher-signed snapshot, not a blanket version bypass. A passing CI fixture does not replace actual cPanel acceptance.

## Customer messaging

"Your signed demo content can be reused across application updates as long as its content format and your database are compatible. A new demo package is required only when the import format or required data changes. Licensing, signature verification and backup protection always apply."
