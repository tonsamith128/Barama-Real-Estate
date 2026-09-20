# Real Estate Domain Model

This document defines the canonical domain boundaries introduced in v0.3.0-dev.

## Core principle

A property and a listing are not the same record.

A **property** represents the physical real-estate asset and its durable attributes: property type, location, project relationship, bedrooms, bathrooms, size, furnishing, construction year, address, coordinates, and descriptive content.

A **listing** represents a market offer for that property: sale or rent, asking price, currency, rental pricing period, deposit, availability, publication state, featured period, expiry, and close date.

This separation allows the same property to have different offers over time without overwriting the property's identity or history.

## Entities

### Location

Locations form a self-referencing hierarchy and support country, province/state, city, district, commune, and neighborhood levels. Slugs are globally unique in the current canonical model.

### Amenity

Amenities are reusable catalog records. Projects and properties attach amenities through dedicated many-to-many tables.

### Company / Developer

The companies table is the canonical business entity for developers, agencies, property companies, and owner organizations.

company_members links authenticated users to companies. The membership role is company-scoped and is separate from the global authentication role/permission system.

Public company profiles should only expose records that have passed the appropriate status/publication checks.

### Project / Development

Projects belong to one company and can optionally reference a location. Project lifecycle and construction/completion state are separate concepts.

### Property

Properties may belong to a company, a project, an individual owner account, and a location. The property status controls review/approval of the asset record. Commercial offer state belongs to listings, not properties.

### Listing

Listings belong to a property. A listing is a sale or rent offer and owns price, currency, availability, publication, feature, expiration, and close state.

Multiple historical listings can belong to one property. Application services must prevent conflicting simultaneously published offers when product rules require exclusivity.

### Media

media_assets stores file metadata only. media_attachments attaches media to a supported entity type and collection. Supported entity types are defined by DomainRules and not accepted directly from untrusted input.

Upload validation/storage hardening remains a separate implementation phase; this schema does not imply that arbitrary uploads are safe.

### Inquiry

An inquiry is an inbound contact/message and can target a company, project, property, or listing. Inquiries remain the immutable entry point for customer interest and can exist without an authenticated user.

Application validation must require at least one meaningful target and at least one contact channel where appropriate.

### Lead

A lead is the qualified/follow-up business record owned by a company. An inquiry may be promoted to at most one lead. Lead stages provide a small sales pipeline foundation without treating the product as a full CRM.

## Lifecycle rules

Canonical status values and allowed transitions live in:

application/app/Domain/RealEstate/DomainRules.php

Database columns intentionally use VARCHAR rather than database ENUM types. This keeps migrations portable across supported MySQL/MariaDB versions while centralizing application validation in one canonical place.

## Authorization

Developer permissions added in this phase:

- company.manage
- project.manage
- property.manage
- listing.manage
- media.manage
- inquiry.manage
- lead.manage

Administrator additionally receives:

- location.manage
- amenity.manage

Global permissions only answer whether a user may enter a capability area. Company membership and company-scoped ownership must still be enforced by domain services before any developer mutation.

## Delete policy

Primary commercial entities include deleted_at and should use soft deletion in normal application workflows.

Foreign keys use RESTRICT where deleting a parent would destroy commercial integrity, SET NULL where historical records can remain valid without the optional relation, and CASCADE only for true dependent/pivot records.
