# Service Catalog -- Data Model

> **⚠️ SUPERSEDED** — Service economics are now modelled via `pet_roles` (internal cost), `pet_service_types` (classification), and `pet_rate_cards` (sell pricing). See `07_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md`. Table retained for legacy read access only.

## Table: service_catalog_items (legacy)

-   id (UUID, PK)
-   name (varchar 255, required)
-   department_id (UUID, FK, required)
-   base_internal_rate (decimal 12,2, required)
-   recommended_sell_rate (decimal 12,2, required)
-   skill_level_id (UUID, FK, optional)
-   status (enum: active, archived)
-   created_at (datetime)
-   updated_at (datetime)

## Rules

-   Economic fields snapshotted when used in Quote.
-   Sell rate deviation triggers approval rule.
