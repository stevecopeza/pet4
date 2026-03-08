# PET – Event Registry

## Purpose
Authoritative list of all valid PET domain events.

## Rules
- Event names are canonical
- Payloads are immutable
- No undocumented events allowed
 - Outbox payloads wrap domain events with idempotency and retry metadata

## Events

### quote.accepted
- Aggregate: Quote
- Trigger: Quote accepted
- Payload: quote_id, accepted_by, accepted_at

### time.submitted
- Aggregate: TimeEntry
- Trigger: Time submission
- Payload: time_entry_id, employee_id, minutes

### ticket.warning
- Aggregate: Ticket
- Trigger: SLA warning threshold reached
- Payload: ticket_id

### ticket.breached
- Aggregate: Ticket
- Trigger: SLA breach threshold reached
- Payload: ticket_id

### ticket.escalation_triggered
- Aggregate: Ticket
- Trigger: Escalation rule condition met
- Payload: ticket_id, level

### delivery.milestone_completed
- Aggregate: Project
- Trigger: Milestone completion criteria met
- Payload: project_id, milestone_id

### commercial.change_order_approved
- Aggregate: ChangeOrder
- Trigger: Change Order approved
- Payload: change_order_id, approved_by

### delivery.project_created
- Aggregate: Project
- Trigger: Quote acceptance (converted to project)
- Payload: project_id, source_quote_id

## Outbox Event Contracts

- Destination: quickbooks
- Outbox row: id, event_id(FK), destination, status(pending|sent|failed|dead), attempt_count, next_attempt_at, last_error, created_at, updated_at
- Payload envelope includes event_uuid, aggregate_type/id/version, event_type, occurred_at, schema_version, idempotency_key, and data block (customer_id, period window, items with source, description, quantity, unit_price, amount, qb_item_ref, total_amount)
- Idempotency: key = aggregate_type + aggregate_id + aggregate_version; duplicates ignored; event_uuid unique
- Retry: exponential backoff (1m, 5m, 30m, 2h); after 6 failures → dead
- Success events: BillingExportSentToQuickBooks, QuickBooksInvoiceSnapshotRecorded
- Failure events: BillingExportDispatchFailed, OutboxDispatchFailedTerminal

**Authority**: Normative
