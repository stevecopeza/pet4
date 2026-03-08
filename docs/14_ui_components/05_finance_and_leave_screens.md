# UI Contracts – Finance & Leave

## Billing Export Detail
- Status badge, period, sortable items, server-calculated totals
- Dispatch attempts log
- Retry visible only if status=failed
- No editing when status != draft

## QB Invoices View
- Read-only; filter by balance>0
- Show last_synced_at
- Link to related billing export when mapping exists

## Leave Screens
- My Leave: create draft, submit, cancel (if allowed)
- Manager View: approve, reject, decision history
- Calendar Overlay:
  - approved leave (red)
  - pending leave (amber)
  - holidays (grey)

## Approvals Queue
- Pending first
- Show subject_type + subject_id
- Decision buttons
- Immutable history panel

**Authority**: Normative
