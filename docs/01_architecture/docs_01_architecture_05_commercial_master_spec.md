# PET -- Commercial Architecture v1.0 Master Specification

## 1. Purpose

This document defines the complete Commercial → Contract → Delivery
architecture for PET. It consolidates catalog, quoting, approval,
contract formation, baseline governance, variance control, procurement
triggering, and revenue scheduling into a single coherent model.

This specification is authoritative.

------------------------------------------------------------------------

# 2. Architectural Overview

Service Catalog (economic source of truth) → Quote (versioned
negotiation artifact) → Approval Rule Engine (governance) → Contract
(binding commercial artifact) → Baseline v1 (delivery constraint) →
Project (execution domain) → Variance / Change Orders (controlled drift)
→ Payment Schedule (canonical revenue model) → ProcurementIntent
(downstream operational trigger)

Core principles: - Immutability after acceptance - Snapshot at boundary
transitions - Explicit variance - No silent mutation - Template vs
Instance separation

------------------------------------------------------------------------

# 3. Service Catalog Domain

Defines sellable services and economic baselines.

## Entity: ServiceCatalogItem

Mandatory fields: - id (UUID) - name - department_id -
base_internal_rate - recommended_sell_rate - status

Rules: - Sell rate deviation triggers Approval Rule Engine. - Rates
snapshotted when used in Quote. - Catalog edits never alter historical
Quotes.

------------------------------------------------------------------------

# 4. Quote Domain

Quote is a fully versioned negotiation artifact.

## Characteristics

-   Component-based structure
-   Economic modelling at task level
-   Approval-governed
-   Immutable once accepted

## Quote States

draft → pending_approval → approved → sent → accepted → rejected →
superseded

## Versioning

Every material change creates a deep clone. Previous version marked
superseded. No partial mutation.

------------------------------------------------------------------------

# 5. Quote Components

## Component Types

-   CatalogComponent
-   ImplementationComponent (WBS)
-   RecurringServiceComponent
-   CommercialAdjustmentComponent

Each component stores: - sell_value - internal_cost - margin impact

------------------------------------------------------------------------

# 6. Implementation Blueprint (WBS)

Structure: Milestone → Task

Task fields: - title - description - duration_hours -
role_catalog_item_id - base_rate_snapshot - sell_rate_snapshot -
internal_cost_snapshot - sell_value_snapshot

Rules: - Role-based only (no individuals). - Internal cost ceiling
derived at sale. - All economic values snapshotted.

------------------------------------------------------------------------

# 7. Recurring Services

Fields: - service_name - SLA_snapshot_json - cadence - term_length -
renewal_model - sell_price - internal_cost

Rules: - SLA version snapshotted at sale. - Renewal configurable per
service.

------------------------------------------------------------------------

# 8. Payment Plan Engine

Sales selects trigger model: - Deposit - Milestone-based - Date-based -
Percentage-based - Completion event

System generates canonical schedule: - due_date - amount -
trigger_reference (optional)

Canonical schedule stored in Contract. Never recalculated
post-acceptance.

------------------------------------------------------------------------

# 9. Approval Rule Engine

Evaluated on Quote save.

Triggers may include: - Margin below threshold - Sell rate below
recommended - Deal size threshold - Payment term deviation

Rules configurable via admin settings. Approval events logged.

------------------------------------------------------------------------

# 10. Contract Domain

Contract is the binding commercial artifact.

Created automatically on QuoteAccepted.

Includes snapshots of: - Commercial totals - Payment schedule - SLA
version - Baseline reference

## Contract States

draft → active → suspended → terminated → completed

Immutable except via Contract Amendment.

------------------------------------------------------------------------

# 11. Baseline Governance

Baseline v1 created at acceptance.

Contains: - Sold WBS - Internal cost ceiling - Margin at sale

Re-baseline only via explicit action. Historical baselines preserved.

------------------------------------------------------------------------

# 12. Variance & Change Control

## VarianceOrder

-   Delivery-only internal variance
-   Does not alter contract price
-   Margin erosion visible

## ChangeOrder

-   Commercial amendment
-   Creates new Quote version
-   Requires approval
-   Produces Contract Amendment
-   May trigger re-baseline

Clear separation between commercial and operational drift.

------------------------------------------------------------------------

# 13. ProcurementIntent

Generated on Quote acceptance for catalog items.

Fields: - supplier_id - contract_id - bundling_group_id - status

System auto-suggests bundling by supplier. Human confirmation required.

------------------------------------------------------------------------

# 14. Snapshot Model

## Quote Versioning

Full deep clone on change.

## Contract Creation

On acceptance: - Snapshot commercial totals - Snapshot SLA - Generate
canonical payment schedule - Create Baseline v1

## Baseline Versioning

Explicit re-baseline only.

Snapshots stored both relationally and as JSON freeze copies.

------------------------------------------------------------------------

# 15. Domain Events

Events are immutable.

Key events: - QuoteAccepted - ContractCreated - VarianceOrderCreated -
ChangeOrderCreated - BaselineCreated - ProcurementIntentCreated

All compliance and financial boundary transitions are evented.

------------------------------------------------------------------------

# 16. Invariants

-   Accepted Quotes immutable.
-   Contract pricing immutable unless amended.
-   Payment schedules canonical and fixed.
-   Internal cost ceiling enforced with explicit variance.
-   SLA bound at sale version.
-   No mutation of historical financial assumptions.

------------------------------------------------------------------------

# 17. System Integrity Statement

Commercial Architecture v1.0 ensures:

-   What was sold constrains what is delivered.
-   Revenue schedule reflects negotiated structure.
-   Margin visibility is preserved.
-   Governance is rule-driven, not discretionary.
-   Change is explicit and evented.
-   Historical truth is never rewritten.

This completes the Commercial operating layer of PET.
