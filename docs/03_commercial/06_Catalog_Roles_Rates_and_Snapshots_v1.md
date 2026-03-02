STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Catalog, Roles, Rates, and Snapshots (v1)

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
- Draft quote ticket stores a reference to the quote snapshot.
- Baseline accepted ticket stores a pointer to the accepted snapshot (or embeds immutable sold fields).

## Conversions and rate derivation

- Hourly rate is canonical.
- Day/week quantities convert using configurable settings.
- UI may display days/weeks; storage must be deterministic and audit-safe.

## Guardrail: no retroactive catalog mutation

Catalog item changes affect only future quotes.
Accepted quote snapshots never change.
