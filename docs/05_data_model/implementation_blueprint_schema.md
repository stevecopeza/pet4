# Implementation Blueprint -- Schema

## Tables

### quote_milestones

-   id (UUID, PK)
-   quote_component_id (UUID, FK)
-   name (varchar 255)
-   description (text)
-   sequence (int)

### quote_tasks

-   id (UUID, PK)
-   milestone_id (UUID, FK)
-   title (varchar 255)
-   description (text)
-   duration_hours (decimal 8,2)
-   role_id (BIGINT, FK → pet_roles) — renamed from role_catalog_item_id
-   base_internal_rate (decimal 12,2) — snapshot from Role
-   sell_rate (decimal 12,2) — snapshot from RateCard
-   internal_cost_snapshot (decimal 14,2)
-   sell_value_snapshot (decimal 14,2)
-   sequence (int)

## Rules

-   All rate values snapshotted.
-   Internal cost ceiling derived at sale.
