STATUS: SUPERSEDED
SCOPE: Ticket Backbone Correction
VERSION: v1
SUPERSEDED BY: 07_Products_Roles_ServiceTypes_and_RateCards_v2.md

# Catalog, Roles, Rates, and Snapshots (v1)

> **⚠️ SUPERSEDED** — This document has been replaced by [Products, Roles, Service Types, and Rate Cards (v2)](07_Products_Roles_ServiceTypes_and_RateCards_v2.md). The single-catalog GOODS/LABOUR model described here is no longer the target architecture. Products are now in a dedicated `CatalogProduct` entity; labour economics are modelled via Role (internal cost), ServiceType (classification), and RateCard (sell pricing). See v2 for the authoritative specification.

## Typed catalog requirement

A single catalog supports multiple kinds:

- GOODS: hardware/software resale (SKU, qty, cost, markup, RRP)
- LABOUR: services (role/department/skill/rate, unit in time)

## GOODS fields (normative)

- `title`, `description`
- `sku`
- `purchase_price`
- `markup` and/or `margin`
- `recommended_retail_price`
- `default_quantity` (optional)

## LABOUR fields (normative)

- `title`, `description`
- `department_id`
- `skill_level`
- `role_id` (optional but supported)
- `base_hourly_rate` (or `rate_plan_id`)
- `default_unit` ENUM('hour','day','week')
- `default_unit_hours` INT (configurable; default 8/40 conversion logic elsewhere)

## Roles and responsibilities

- Roles are created and assigned to people.
- Quotes may assign roles to labour line items/tickets as staffing intent.
- Tickets may carry `required_role_id` for scheduling and staffing.

## Snapshot rules (immutability)

### Quote snapshot
Quote lines/tasks must store immutable snapshot fields sufficient to rebuild “what was sold”, including:
- title/description
- role name/id at time
- department/skill at time
- rates and commercial flags
- quantities and unit conversions used

After acceptance, these snapshots are never edited.

### Ticket linkage
- No tickets exist during quoting. Quote tasks are managed by the quote builder.
- On acceptance, tickets are created with immutable `sold_minutes` and `sold_value_cents` snapshotted from the accepted quote task.
- The ticket's `quote_id` links back to the accepted quote for traceability.

## Conversions and rate derivation

- Hourly rate is canonical.
- Day/week quantities convert using configurable settings.
- UI may display days/weeks; storage must be deterministic and audit-safe.

## Guardrail: no retroactive catalog mutation

Catalog item changes affect only future quotes.
Accepted quote snapshots never change.
