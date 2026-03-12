# ProcurementIntent -- Schema

## Table: procurement_intents

-   id (UUID, PK)
-   supplier_id (UUID, FK)
-   contract_id (UUID, FK)
-   bundling_group_id (UUID, nullable)
-   status (enum: draft, confirmed, ordered, received)

## Rules

-   Bundling auto-suggested, human confirmed.
