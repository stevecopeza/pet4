# PET Commercial Engine v1.0 -- Developer Execution Specification

Status: Authoritative Scope: Quote → Approval → Contract → Baseline →
Forecast → Delivery Transition Audience: Developers, Architects,
Technical Leads

This document consolidates all commercial-layer behaviour discussed and
locked.

  ---------------------
  1\. CORE PRINCIPLES
  ---------------------

1.  Quotes are versioned and immutable once accepted.
2.  Contracts are binding artifacts derived from accepted Quotes.
3.  Baselines preserve sold structure.
4.  No silent mutation of financial assumptions.
5.  Commercial change and delivery variance are separate concepts.
6.  Forecast, Committed, Scheduled, and Actual capacity are distinct
    states.
7.  Service Catalog is economic source of truth.
8.  Internal cost overrides require explicit adjustment entities.
9.  All boundary transitions emit immutable domain events.

  ----------------------------------
  2\. ENTITY RELATIONSHIP OVERVIEW
  ----------------------------------

Opportunity → has many Quotes (versioned) Quote → has many Components →
linked to Opportunity (required) QuoteAccepted → creates Contract
ContractActive → creates Committed Capacity QuoteApproved → creates
Forecast Capacity Contract → generates Baseline v1 Baseline →
instantiates Project Project → tracks VarianceOrders

  ----------------------------------------------
  3\. DATA MODEL -- COMPLETE FIELD DEFINITIONS
  ----------------------------------------------

3.1 Opportunity - id (UUID, PK) - customer_id (UUID, FK) - stage
(enum) - probability (decimal 5,2) - expected_close_date (date) -
owner_id (UUID)

3.2 Quote - id (UUID, PK) - opportunity_id (UUID, FK, required) -
quote_number (varchar 50, unique) - title (varchar 255, required) -
description (text, required) - currency (char 3, required) - valid_from
(date, required) - valid_until (date, required) - version_number (int) -
supersedes_quote_id (UUID, nullable) - status (enum: draft,
pending_approval, approved, sent, accepted, rejected, superseded) -
total_sell_value (decimal 14,2) - total_internal_cost (decimal 14,2) -
total_margin (decimal 14,2) - created_by (UUID) - created_at
(datetime) - updated_at (datetime)

3.3 QuoteComponent - id (UUID) - quote_id (UUID) - component_type (enum:
catalog, implementation, recurring, adjustment) - sort_order (int) -
sell_value (decimal 14,2) - internal_cost (decimal 14,2)

3.4 Implementation Milestone - id (UUID) - quote_component_id (UUID) -
name (varchar 255) - description (text) - sequence (int)

3.5 Implementation Task - id (UUID) - milestone_id (UUID) - title
(varchar 255) - description (text) - duration_hours (decimal 8,2) -
role_catalog_item_id (UUID) - department_snapshot (varchar 255) -
base_rate_snapshot (decimal 12,2) - sell_rate_snapshot (decimal 12,2) -
internal_cost_snapshot (decimal 14,2) - sell_value_snapshot (decimal
14,2) - sequence (int)

3.6 RecurringServiceComponent - id (UUID) - quote_component_id (UUID) -
service_name (varchar 255) - sla_snapshot_json (json) - cadence (enum) -
term_months (int) - renewal_model (enum) - sell_price (decimal 14,2) -
internal_cost (decimal 14,2)

3.7 Contract - id (UUID) - originating_quote_id (UUID) - customer_id
(UUID) - status (enum: draft, active, suspended, terminated,
completed) - effective_date (date) - commercial_snapshot_json (json) -
sla_snapshot_json (json) - baseline_id (UUID)

3.8 Baseline - id (UUID) - project_id (UUID) - version_number (int) -
internal_cost_ceiling (decimal 14,2) - created_at (datetime)

3.9 VarianceOrder - id (UUID) - project_id (UUID) - amount (decimal
14,2) - reason (text) - approved_by (UUID)

3.10 ForecastCapacity - id (UUID) - quote_id (UUID) - role_id (UUID) -
department_id (UUID) - forecast_hours (decimal 8,2) - probability_weight
(decimal 5,2) - time_window (date range)

  -----------------------------------------------
  4\. QUOTE BUILDER UX CONTRACT (SECTION MODEL)
  -----------------------------------------------

Step 1: Header Required - Customer - Title - Description - Currency -
Validity

Step 2: Add Section Options: - Once-Off Product (Catalog) -
Implementation Plan (WBS) - Recurring Service - Commercial Adjustment

Implementation Section: - Milestone → Task hierarchy - Hours + role
selection - Sell auto-calculated - Margin shown live

Step 3: Payment Plan - Explicit selection required - Canonical schedule
generated - Must confirm before submission

Readiness Gate: - At least one component - Payment plan generated -
Margin \>= 0 - Required fields complete

Approval Gate: - Margin below threshold - Sell override - Cost
adjustment - Payment deviation

  --------------------------
  5\. FORECAST INTEGRATION
  --------------------------

On QuoteApproved: - Extract WBS hours - Weight by Opportunity
probability - Insert ForecastCapacity records

On QuoteSuperseded: - Replace forecast

On QuoteRejected: - Remove forecast

On ContractActive: - Convert Forecast → Committed

Thresholds: - Warning at 85% - Escalation at 100%

  ----------------------------
  6\. COMMERCIAL TRANSITIONS
  ----------------------------

QuoteAccepted: - Create Contract - Snapshot commercial totals - Snapshot
SLA - Generate Baseline v1 - Emit events

ChangeOrder: - New Quote version - Approval required - Contract
amended - Optional re-baseline

VarianceOrder: - Internal overrun only - No contract price impact

  -------------------
  7\. DOMAIN EVENTS
  -------------------

-   QuoteCreated
-   QuoteApproved
-   QuoteAccepted
-   QuoteRejected
-   ContractCreated
-   ContractActivated
-   BaselineCreated
-   VarianceOrderCreated
-   ChangeOrderCreated
-   ForecastCapacityCreated

All immutable.

  ----------------
  8\. INVARIANTS
  ----------------

-   Accepted Quote immutable.
-   Contract pricing immutable unless amended.
-   Baseline changes require explicit re-baseline.
-   Internal cost never silently modified.
-   Templates deep-cloned and versioned.
-   Forecast distinct from committed capacity.

------------------------------------------------------------------------

END OF SPECIFICATION
