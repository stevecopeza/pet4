# Service Catalog -- Data Model

## Table: service_catalog_items

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
