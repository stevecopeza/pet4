# Quote -- Relational Schema

## Table: quotes

-   id (UUID, PK)
-   quote_number (varchar 50, unique)
-   customer_id (UUID, FK)
-   version_number (int)
-   supersedes_quote_id (UUID, FK, nullable)
-   status (enum: draft, pending_approval, approved, sent, accepted,
    rejected, superseded)
-   valid_from (date)
-   valid_until (date)
-   currency (char 3)
-   total_sell_value (decimal 14,2)
-   total_internal_cost (decimal 14,2)
-   total_margin (decimal 14,2)
-   created_by (UUID)
-   created_at (datetime)

## Immutability

-   Fully immutable once accepted.

## Table: quote_catalog_items

Stores line items for catalog-based components.

-   id (UUID, PK)
-   component_id (UUID, FK)
-   catalog_item_id (UUID, FK, nullable) - Link to source catalog item
-   type (enum: product, service)
-   description (varchar 255)
-   sku (varchar 50, nullable) - **Required for Product items**
-   role_id (int, nullable) - **Required for Service items**
-   quantity (decimal 12,2)
-   unit_sell_price (decimal 14,2)
-   unit_internal_cost (decimal 14,2)
-   wbs_snapshot (json) - Snapshot of Work Breakdown Structure if applicable

### Invariants
-   **Products**: Must have `sku`. Cannot have `wbs_snapshot`.
-   **Services**: Must have `role_id`.

