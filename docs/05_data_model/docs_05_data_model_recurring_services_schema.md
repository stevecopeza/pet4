# Recurring Services -- Schema

## Table: quote_recurring_services

-   id (UUID, PK)
-   quote_component_id (UUID, FK)
-   service_name (varchar 255)
-   sla_snapshot_json (json)
-   cadence (enum)
-   term_months (int)
-   renewal_model (enum)
-   sell_price (decimal 14,2)
-   internal_cost (decimal 14,2)

## Rules

-   SLA version snapshotted at sale.
