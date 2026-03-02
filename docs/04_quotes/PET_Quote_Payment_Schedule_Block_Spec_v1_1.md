# PET Quote Payment Schedule Block Specification (v1.1)

## Architectural Position
Payment Schedule is a dedicated Quote Block (0..1 per quote version).
Immutable after acceptance.
Additive corrections only.

Basis: QUOTE_GRAND_TOTAL only.

## Amount Modes
- FIXED
- PERCENT_OF_BASIS
- EQUAL_INSTALLMENTS_OVER_PERIOD

## Trigger Types
- ON_ACCEPTANCE
- ON_DATE
- ON_DOMAIN_EVENT
- ON_INSTALLMENT_SERIES

## Installment Series Rules
- Requires projected_finish_date (derived or manual)
- Materialize concrete installment items before acceptance
- 2-decimal rounding
- Final installment may include deterministic rounding delta

## Acceptance Hard Block
- Sum(computed_amounts) == Quote.grand_total exactly
- Fail-fast for percent mismatch
- Installment series must reconcile exactly

## Domain Events
- PaymentScheduleDefinedEvent
- PaymentScheduleItemBecameDueEvent

## Persistence
Custom tables only.
Forward-only migrations.
Backward compatible.

## Mermaid — Acceptance Flow
```mermaid
flowchart TD
  A[Draft Quote] --> B{Schedule Exists?}
  B -- No --> C[Normal Readiness]
  B -- Yes --> D[Compute Amounts]
  D --> E{Sum == Grand Total?}
  E -- No --> F[BLOCK Acceptance]
  E -- Yes --> G[Accept Quote]
  G --> H[Emit Due Events]
```

## Mermaid — Installment Materialization
```mermaid
flowchart TD
  A[Installment Series Defined] --> B[Validate Period]
  B --> C[Generate Dates]
  C --> D[Compute Equal Amounts]
  D --> E[Apply Final Delta]
  E --> F[Persist Items]
```
