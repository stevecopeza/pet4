# Contract -- Schema

## Table: contracts

-   id (UUID, PK)
-   originating_quote_id (UUID, FK)
-   customer_id (UUID, FK)
-   status (enum: draft, active, suspended, terminated, completed)
-   effective_date (date)
-   commercial_snapshot_json (json)
-   sla_snapshot_json (json)
-   baseline_id (UUID, FK)

## Rules

-   Created automatically on QuoteAccepted.
-   Immutable except via amendment.
