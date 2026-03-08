STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# SLA / Agreement / Entitlement Drawdown (v1)

## Terms

- **SLA Template**: generic policy (response/resolution targets, calendar).
- **Agreement**: customer contract defining recurring commercial terms (included hours, discounts).
- **Snapshot**: immutable binding of template + calendar + targets at time of agreement/quote.

## Current foundations (audit)

- `wp_pet_slas` (templates)
- `wp_pet_contract_sla_snapshots` (bound snapshots)
- tickets have `sla_snapshot_id`
- SLA clock state per ticket exists

## Missing to meet the operating model

To support “100 hours per month” and “discount from hour 1”:

### Agreement entity (required)
Create `wp_pet_agreements` (or similar):
- customer_id
- status
- start/end
- billing cadence
- included_minutes_per_period (nullable)
- discount rules (percentage/tiers)
- rate plan linkage
- sla_snapshot_id binding
- regeneration policy

### Entitlement ledger (required)
Create `wp_pet_entitlement_ledger`:
- agreement_id
- period_start, period_end
- granted_minutes
- consumed_minutes
- carried_over_minutes (optional)
- created_at

### Consumption records (required)
Create `wp_pet_entitlement_consumptions`:
- agreement_id
- time_entry_id
- ticket_id
- consumed_minutes
- created_at

## Rules

- Ticket may reference an agreement via `agreement_id`.
- Time entries against tickets with agreement_id:
  - consume entitlement first (if included minutes exist)
  - remaining minutes billable per agreement rules
- Overages require explicit approval (commercial adjustment) before invoicing/export.

## No recurring tickets requirement

Agreement regenerates entitlement. Tickets are created as work is requested, not per period.

## Mermaid

```mermaid
flowchart LR
  A[Agreement] --> L[Entitlement Ledger (period)]
  T[Ticket] -->|agreement_id| A
  TE[Time Entry] -->|ticket_id| T
  TE --> C[Consumption Record]
  C --> L
```
