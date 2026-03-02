# QuickBooks-First Finance Model (PET is not Accounting)

## Authority Split
- PET owns billable completion facts, billing export batches, external mappings, and customer-facing finance visibility (read models).
- QuickBooks owns authoritative invoices, numbering, tax, AR ledger, payments, and credit notes.

## Core Tables (Additive)
- pet_external_mappings: unique mapping between PET entities and QuickBooks IDs; enforces idempotent push/pull.
- pet_integration_runs: records push/pull runs, status, summaries, errors.
- pet_billing_exports: export packages (draft→queued→sent|failed→confirmed).
- pet_billing_export_items: immutable after queue; source from time entries, baseline components, adjustments.
- pet_qb_invoices: read-only shadow; upserted from QB payloads.
- pet_qb_payments: read-only shadow; upserted from QB payloads.

## Application Commands (Examples)
- CreateBillingExport, AddBillingExportItem, QueueBillingExportForQuickBooks
- RecordQuickBooksInvoiceSnapshot, RecordQuickBooksPaymentSnapshot
- RunQuickBooksSyncPush, RunQuickBooksSyncPull

## Invariants & Rules
- Additive migrations only; no destructive changes.
- BillingExport edits allowed only in draft; queued exports immutable.
- Shadow tables are read-only; reconciliation via raw_json, last_synced_at, mappings.
- Idempotency enforced via unique keys (external IDs, export UUIDs).

## UI Contracts
- Billing Exports: list/detail, add items, queue, retry, confirm.
- QuickBooks Invoices/Payments: read-only views with filters; link to originating export via mapping.

## Non-Goals
- No journals, general ledger, authoritative tax, or AR replacement.

## Phases (Demo-Driven)
- Phase 1: event backbone, external mappings, integration runs, billing exports/items, QB invoice/payment shadows, minimal QB mock, basic UI.
- Phase 2: governance realism (approvals), leave/capacity integration, sensitive action approval.
- Phase 3: production hardening (projection worker, webhooks/scheduled pulls, reconciliation screens), optional pipeline (opportunities/CRM).
