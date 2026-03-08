# PET Gap Resolution Specification
Date: 2026-02-14  
Status: PROPOSED  

Purpose:
This document closes the documentation gaps identified in the implementation plan.  
It defines authoritative contracts and invariants so implementation is deterministic and testable.

---

# 1) OUTBOX PAYLOAD SCHEMA & EVENT CONTRACTS

## 1.1 Outbox Row Structure (Authoritative)

Destination: quickbooks

Required columns:
- id
- event_id (FK → pet_domain_event_stream.id)
- destination = 'quickbooks'
- status: pending|sent|failed|dead
- attempt_count (int)
- next_attempt_at (datetime)
- last_error (text nullable)
- created_at
- updated_at

## 1.2 Payload Envelope (payload_json)

{
  "event_uuid": "UUID",
  "aggregate_type": "billing_export",
  "aggregate_id": 123,
  "aggregate_version": 2,
  "event_type": "BillingExportQueued",
  "occurred_at": "ISO8601",
  "schema_version": 1,
  "idempotency_key": "billing-export-123-v2",
  "data": {
      "customer_id": 10,
      "period_start": "YYYY-MM-DD",
      "period_end": "YYYY-MM-DD",
      "currency": "ZAR",
      "items": [
          {
              "source_type": "time_entry",
              "source_id": 99,
              "description": "...",
              "quantity": 3.0,
              "unit_price": 1650.00,
              "amount": 4950.00,
              "qb_item_ref": "SRV-CONSULT-01"
          }
      ],
      "total_amount": 185000.00
  }
}

## 1.3 Idempotency Rules

- idempotency_key = aggregate_type + aggregate_id + aggregate_version
- Dispatcher MUST ignore duplicate idempotency_key.
- event_uuid UNIQUE at DB level.

## 1.4 Retry Strategy

- Exponential backoff:
  attempt 1: +1 min
  attempt 2: +5 min
  attempt 3: +30 min
  attempt >=4: +2 hours
- After 6 failed attempts → status=dead
- Emit event: OutboxDispatchFailedTerminal

## 1.5 Success / Failure Events

On success:
- BillingExportSentToQuickBooks
- QuickBooksInvoiceSnapshotRecorded

On failure:
- BillingExportDispatchFailed (non-terminal)
- OutboxDispatchFailedTerminal (terminal)

---

# 2) QUICKBOOKS MAPPING RULES

## 2.1 Item Mapping Defaults

- qb_item_ref = catalog SKU if present
- If missing:
  fallback = "GEN-SERVICE"
- If still missing:
  fail export (do not silently continue)

## 2.2 Rounding

- All amounts rounded HALF_UP to 2 decimals before export.
- Sum(line.amount) MUST equal total_amount exactly after rounding.

## 2.3 Tax Handling

- PET does NOT compute authoritative tax.
- Export sends net amounts only.
- QB calculates tax.
- Shadow snapshot stores:
  - qb_tax_total
  - qb_total
  - qb_balance

## 2.4 Snapshot Structure (pet_qb_invoices.raw_json)

{
  "qb_invoice_id": "...",
  "doc_number": "...",
  "status": "Open|Paid|Overdue",
  "currency": "ZAR",
  "total": 185000.00,
  "balance": 65000.00,
  "line_items": [...],
  "updated_at": "..."
}

Shadow tables are READ-ONLY in PET.

---

# 3) LEAVE STATE MACHINE

States:
draft → submitted → approved|rejected|cancelled

## 3.1 Allowed Transitions

draft → submitted  
submitted → approved  
submitted → rejected  
approved → cancelled (creates compensating record)  
rejected → cancelled  
draft → cancelled  

Illegal transitions MUST throw hard errors.

## 3.2 Required Fields

On submit:
- start_date
- end_date
- leave_type_id

On approve:
- approved_by_employee_id
- approved_at
- decision_reason (optional)

On reject:
- approved_by_employee_id
- approved_at
- decision_reason (required)

## 3.3 Emitted Events

- LeaveSubmitted
- LeaveApproved
- LeaveRejected
- LeaveCancelled

Events append-only.

---

# 4) CAPACITY OVERRIDE SEMANTICS

## 4.1 Precedence Order (highest wins)

1. CapacityOverride (specific date)
2. Approved Leave
3. Holiday
4. Calendar Working Window

## 4.2 Effective Capacity Formula

For a given employee on a date:

base_hours = working_window_hours  
if holiday → base_hours = 0  
if approved_leave overlaps → base_hours = 0  
if override exists → base_hours = working_window_hours * (capacity_pct / 100)

EffectiveCapacity(date) = base_hours

Overrides DO NOT stack.

## 4.3 Utilization API Output

{
  "employee_id": 3,
  "date": "YYYY-MM-DD",
  "effective_capacity_hours": 6.8,
  "scheduled_hours": 5.0,
  "utilization_pct": 73.5
}

---

# 5) REST CONTRACTS

## 5.1 Pagination

Query params:
- page (int, default 1)
- per_page (int, max 100)
- sort_by
- sort_direction (asc|desc)

Response envelope:

{
  "data": [...],
  "meta": {
      "page": 1,
      "per_page": 20,
      "total": 134,
      "total_pages": 7
  }
}

## 5.2 Error Envelope

{
  "error": {
      "code": "VALIDATION_ERROR",
      "message": "Human readable",
      "details": {...}
  }
}

Status codes:
- 400 validation
- 401 auth
- 403 permission
- 404 not found
- 409 illegal state transition
- 500 server error

---

# 6) UI CONTRACTS

## 6.1 Billing Export Detail Screen

Must display:
- Status badge
- Period
- Items (sortable)
- Totals (calculated server-side)
- Dispatch attempts log
- Retry button (visible only if status=failed)

No editing when status != draft.

## 6.2 QB Invoices View

- Read-only
- Filter by balance>0
- Show last_synced_at
- Link to related billing export (if mapping exists)

## 6.3 Leave Screens

My Leave:
- Create draft
- Submit
- Cancel (if allowed)

Manager View:
- Approve
- Reject
- See decision history

Calendar Overlay:
- Color code:
  - approved leave (red)
  - pending leave (amber)
  - holidays (grey)

## 6.4 Approvals Queue

- Pending first
- Show subject_type + subject_id
- Decision buttons
- Immutable history panel

---

# 7) OPEN QUESTIONS (Require Decision)

1. Should leave cancellation after approval:
   A) Fully restore capacity retroactively (recommended)  
   B) Only affect future dates  

2. For failed QuickBooks dispatch after terminal failure:
   A) Require new BillingExport  
   B) Allow manual reset of export status  

3. Should CapacityOverride:
   A) Be per-day only (recommended for simplicity)  
   B) Allow date ranges  

4. Should QB shadow deletions (invoice deleted in QB):
   A) Mark status=deleted  
   B) Remove row entirely  

Please confirm choices so documentation can be finalized.
