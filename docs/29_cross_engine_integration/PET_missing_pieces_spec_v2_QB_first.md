# PET Missing Pieces Specification Pack v2 (QuickBooks-First Model)

Date: 2026-02-14  
Status: PROPOSED (Becomes authoritative once accepted)

This version supersedes v1.  
v1 should be ignored entirely.

This document defines:

- Event backbone (immutable)
- QuickBooks-first billing model (PET is NOT an accounting system)
- Integration primitives (idempotent, event-driven)
- Approvals engine
- Leave & capacity realism
- Optional commercial pipeline
- Phased implementation plan
- Test strategy (unit + integration + demo-seed validation)

====================================================================
SECTION 1 — FINANCE MODEL (QB-FIRST, NOT ACCOUNTING)
====================================================================

PET owns:
- Billable completion facts
- Billing export batches
- Mapping to QB entities
- Customer-facing finance visibility (shadow read models)

QuickBooks owns:
- Official invoices
- Invoice numbering
- Tax calculations
- AR ledger
- Payment posting
- Credit notes (unless mirrored intentionally)

--------------------------------------------------------------------
1.1 Required Tables (Additive)
--------------------------------------------------------------------

pet_external_mappings
- id (PK)
- system (enum: quickbooks)
- entity_type (varchar)
- pet_entity_id (bigint)
- external_id (varchar)
- external_version (varchar nullable)
- created_at
- updated_at
- UNIQUE(system, entity_type, pet_entity_id)
- UNIQUE(system, entity_type, external_id)

pet_billing_exports
- id (PK)
- uuid
- customer_id
- period_start
- period_end
- status (draft, queued, sent, failed, confirmed)
- created_at
- updated_at

pet_billing_export_items
- id (PK)
- export_id (FK)
- source_type (time_entry, baseline_component, adjustment)
- source_id
- quantity
- unit_price
- amount
- description
- qb_item_ref nullable
- status (pending, exported, failed)
- created_at

pet_qb_invoices (Read model)
- id (PK)
- customer_id
- qb_invoice_id
- doc_number
- status
- issue_date
- due_date
- currency
- total
- balance
- raw_json
- last_synced_at

pet_qb_payments (Read model)
- id (PK)
- customer_id
- qb_payment_id
- received_date
- amount
- currency
- applied_invoices_json
- raw_json
- last_synced_at

pet_integration_runs
- id (PK)
- system
- direction (push, pull)
- status (running, success, failed)
- started_at
- finished_at
- summary_json
- last_error

====================================================================
SECTION 2 — EVENT BACKBONE
====================================================================

pet_domain_event_stream (Append-only)
- id (PK)
- event_uuid UNIQUE
- occurred_at
- recorded_at
- aggregate_type
- aggregate_id
- aggregate_version
- event_type
- payload_json
- metadata_json

Rules:
- Insert-only
- No updates or deletes
- Aggregate version monotonic

pet_outbox
- id
- event_id
- destination
- status (pending, sent, failed, dead)
- attempt_count
- next_attempt_at
- last_error

====================================================================
SECTION 3 — APPROVAL ENGINE
====================================================================

pet_approval_requests
- id
- uuid
- request_type
- subject_type
- subject_id
- status (pending, approved, rejected, cancelled)
- requested_by_employee_id
- requested_at
- decided_by_employee_id nullable
- decided_at nullable
- decision_reason nullable
- request_payload_json
- created_at

pet_approval_steps
- id
- approval_request_id
- step_number
- approver_type
- approver_reference_id
- status
- decided_at
- decision_reason
- created_at

Rules:
- No hard deletes
- Status transitions emit domain events

====================================================================
SECTION 4 — LEAVE & CAPACITY
====================================================================

pet_leave_types
- id
- name
- paid_flag
- created_at

pet_leave_requests
- id
- uuid
- employee_id
- leave_type_id
- start_date
- end_date
- status (draft, submitted, approved, rejected, cancelled)
- submitted_at
- approved_by_employee_id
- approved_at
- notes
- created_at
- updated_at

pet_capacity_overrides
- id
- employee_id
- effective_date
- capacity_pct
- reason
- created_at

====================================================================
SECTION 5 — OPTIONAL PIPELINE
====================================================================

pet_opportunities
- id
- customer_id nullable
- lead_id nullable
- name
- stage
- estimated_value
- probability_percent
- expected_close_date
- owner_employee_id
- status
- created_at
- updated_at

pet_crm_activities
- id
- opportunity_id nullable
- lead_id nullable
- customer_id nullable
- activity_type
- subject
- body
- due_at
- completed_at
- owner_employee_id
- created_at

====================================================================
SECTION 6 — UI CONTRACTS
====================================================================

Finance → Billing Exports
- Create draft export
- Add billable items
- Send to QB
- View sync status
- View QB invoice shadow

Finance → QB Invoices
- Read-only view
- Filter by balance > 0
- Link back to delivery source

Governance → Approvals
- List pending
- View immutable payload snapshot
- Approve / Reject

People → Leave
- Request leave
- Manager decision
- Calendar overlay

System → Event Stream
- Filterable event viewer
- View payload JSON
- No edit capability

====================================================================
SECTION 7 — PHASED IMPLEMENTATION
====================================================================

PHASE 1 (Demo-Critical)
- External mappings
- Billing exports + items
- QB invoice + payment shadow tables
- Domain event stream
- Outbox
- Basic approval engine
- Event viewer UI
- Billing export UI
- QB invoice visibility UI

PHASE 2
- Leave requests + approval integration
- Capacity override
- Integration run tracking
- Enhanced approval routing

PHASE 3
- Optional opportunities + CRM
- Forecast models

====================================================================
SECTION 8 — TEST STRATEGY
====================================================================

UNIT TESTS
- Event stream append-only enforcement
- Aggregate version increment validation
- Approval status transition validation
- Billing export state machine
- Mapping uniqueness constraints

INTEGRATION TESTS
- Export batch → outbox → QB mock → mapping stored
- QB pull → shadow invoice update idempotency
- Payment sync updates balance read model
- Duplicate event_uuid rejected

IMMUTABILITY TESTS
- Posted invoice cannot be edited
- Approved leave cannot be edited without new event
- Approved export cannot be modified

DEMO SEED TESTS
- Seeded rows tagged
- Touched rows protected
- Immutable rows preserved after purge
- Purge removes untouched seeded rows only

====================================================================
SECTION 9 — NON-GOALS
====================================================================

PET will NOT:
- Post accounting journals
- Maintain general ledger
- Replace QuickBooks
- Calculate tax as accounting authority

====================================================================

This document is complete for implementation planning and demo preparation.
