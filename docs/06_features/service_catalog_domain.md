# Service Catalog Domain

> **⚠️ SUPERSEDED** — The `ServiceCatalogItem` entity described here has been replaced by the refactored commercial model.

The previous single-entity approach to service pricing has been decomposed into:

- **Role** (Work domain) — carries `base_internal_rate` (internal labour cost)
- **ServiceType** (Commercial domain) — classification of labour (Consulting, Support, Training, etc.)
- **RateCard** (Commercial domain) — sell pricing per (role, service_type, optional contract) with date validity

## Authoritative Specification

See: `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`

That document contains the full structural specification, lifecycle contract, prohibited behaviours, and stress-test scenarios for the replacement entities.

## Key Differences from Previous Model

- `ServiceCatalogItem` is removed — it no longer exists as an entity
- Internal cost (`base_internal_rate`) lives on Role, not in a catalog
- Sell rate (`sell_rate`) lives on RateCard, not in a catalog
- `recommended_sell_rate` is removed — sell rates are policy-driven via RateCards
- Department reference is no longer part of pricing; roles carry their own competency context
- Quotes snapshot from Role + RateCard at line creation time
- Sell rate below threshold still triggers approval rule (evaluated at RateCard resolution)
