STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# Quote → Ticket → Project Flow (v1)

This specifies how quote “cards” become tickets and then projects, with immutable sold baseline.

## Entities (conceptual)

- **CatalogProduct** (products only — hardware, licenses, subscriptions)
- **Role** (competency + `base_internal_rate` for internal cost)
- **ServiceType** (classification of labour — Consulting, Support, Training, etc.)
- **RateCard** (sell pricing per role + service type + optional contract)
- **Quote** (versioned, accepted immutable)
- **QuoteLine / QuoteTask** (snapshot of product catalog or role + rate card economics)
- **Ticket** (draft quote ticket, baseline ticket, execution ticket)
- **Project** (container for execution tickets)
- **Phase** (grouping / payment schedule anchor)

> See `03_commercial/07_Products_Roles_ServiceTypes_and_RateCards_v2.md` for authoritative entity definitions.

## Draft quote building

### Labour items

When user adds labour to a quote:

1. Select a **Role** and **ServiceType**. System resolves the applicable **RateCard** to determine sell rate. `base_internal_rate` is sourced from the Role.
2. Create a **draft QuoteTask** record with snapshotted `base_internal_rate`, `sell_rate`, `service_type_id`, and `rate_card_id`.
3. Create a **draft Ticket** in `wp_pet_tickets` with:
   - `quote_id` = current quote
   - `primary_container` = 'project' OR 'support'?? (normative: 'project' for delivery work)
   - `lifecycle_owner` = 'project'
   - `ticket_kind` = 'work'
   - `status` = 'draft_quote' (or mapped)
   - sold/estimate fields populated from quote task duration and rate snapshot
   - `required_role_id`, `department_id`, etc.

4. Store `ticket_id` on the quote task record.

### Simple vs complex (phases)

- Simple: tickets have `phase_id = NULL` or default phase.
- Complex: phases are explicit groupings; each ticket references a phase.
- Phase totals are derived by summing ticket/quote line snapshots, not by editing tickets post-acceptance.

## Quote acceptance boundary

On `QuoteAccepted`:

1. Freeze quote snapshot (already the case).
2. For every draft quote ticket linked to the accepted quote:
   - Create a **baseline ticket** (immutable sold record) OR mark the existing as baseline-locked.
   - Create corresponding **project execution tickets** (clones) where needed.
3. Create/attach Project:
   - `Project.sourceQuoteId = quote.id`
   - Project gets tickets as the deliverables spine.

### Baseline vs execution tickets

Normative approach:
- Baseline tickets represent “what was sold” (immutable)
- Execution tickets represent “what will be done” and can be broken down

Linkage:
- execution_ticket.baseline_ticket_id (via parent or dedicated link)
- baseline tickets should not accept time directly (roll-up style) or accept only if they remain leaf.

## WBS expansion

When PM breaks down a 100-hour sold ticket into 10x10:

- Baseline ticket remains immutable (sold_minutes=6000)
- Execution parent becomes roll-up (is_rollup=1)
- Ten child execution tickets created with allocated sold/estimate minutes
- Time is logged on children only

## Goods / hardware / software resale

For goods items, create operational tickets if they require work:
- procurement
- delivery
- install
- billing steps

Those tickets may be non-time-loggable if they are purely logistical, but they must exist if human work is involved.

## Payment schedule linkage

Payment schedule items may reference:
- quote total (deposit)
- phase_id
- quote_line_id / baseline_ticket_id

No schedule item may reference mutable execution-only artifacts without a baseline anchor.

## Mermaid overview

```mermaid
flowchart LR
  CP[CatalogProduct] --> Q[Quote Draft]
  R[Role + ServiceType] --> RC[RateCard Resolution]
  RC --> QT[QuoteTask snapshot]
  Q -->|Add product| QPL[QuoteProductLine]
  Q -->|Add labour| QT
  QT --> DT[Draft Ticket (quote_id)]
  Q -->|Accept| QA[Quote Accepted (immutable)]
  QA --> BT[Baseline Tickets (locked)]
  QA --> P[Project created/linked]
  BT --> ET[Execution Tickets (clones)]
  ET -->|Split| CH[Child Execution Tickets]
  CH --> TE[Time Entries (ticket_id)]
```
