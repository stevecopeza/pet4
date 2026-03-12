# PET – QuickBooks Integration possess

## Purpose
Defines the integration contract between PET and QuickBooks.

---

## Authority Boundary

- PET owns invoice intent and time truth
- QuickBooks owns ledger execution and payments

---

## Triggering Events

- InvoiceIntentCreated
- InvoiceIntentUpdated (delta only)

---

## Data Sent to QuickBooks

- Customer identifier
- Line items (mapped from time/products)
- Tax context

### Mapping & Shadows

- qb_item_ref = catalog SKU if present; fallback "GEN-SERVICE"; if missing, fail export
- Amounts rounded HALF_UP to 2 decimals; sum(line.amount) equals total_amount after rounding
- PET sends net amounts only; QuickBooks calculates tax
- Snapshot (read-only) stores: qb_tax_total, qb_total, qb_balance, line_items, updated_at

```
{
  "qb_invoice_id": "...",
  "doc_number": "...",
  "status": "Open|Paid|Overdue",
  "currency": "ZAR",
  "total": 185000.00,
  "balance": 65000.00,
  "tax_total": 15000.00,
  "line_items": [...],
  "updated_at": "..."
}
```

---

## Data Received from QuickBooks

- Invoice status
- Payment confirmation
- Rejection or adjustment notice

---

## Failure Handling

- Sync failures create reconciliation tasks
- No overwrite of PET data

### Deletions

- If invoice deleted in QuickBooks: mark status=deleted in PET shadow; never remove row

---

**Authority**: Normative
