STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v2
SUPERSEDES: v1
DATE: 2026-03-06

# PET — TRAE Ticket Backbone Execution Checklist (v2)

> Governed by `00_foundations/02_Ticket_Architecture_Decisions_v1.md`.

This checklist is designed for sprint-level operational tracking.

---

# Sprint 1 — Schema Foundation

☐ Add columns to wp_pet_tickets  
☐ Create wp_pet_ticket_links  
☐ Add ticket_id to wp_pet_time_entries  
☐ Add ticket_id to wp_pet_tasks  
☐ Add ticket_id to quote labour tables  
☐ Add indexes  
☐ Write migration tests  
☐ Verify idempotency  

---

# Sprint 2 — Backfill Engine

☐ Implement TicketBackfillService  
☐ Create WP-CLI command  
☐ Backfill tasks → tickets  
☐ Backfill time_entries → tickets  
☐ Flag ambiguous rows safely  
☐ Prove re-run safety  

---

# Sprint 3 — Time Enforcement

☐ Modify SubmitTimeEntryHandler  
☐ Resolve ticket via task when possible  
☐ Block submission without ticket  
☐ Block submission on roll-up tickets  
☐ Add integration tests  

---

# Sprint 4 — Quote Acceptance Ticket Creation

> Sprint 4 (Quote Draft Tickets) and Sprint 5 (Quote Acceptance Alignment) from v1 have been merged. No draft tickets are created during quoting per architecture decisions.

☐ Extend QuoteAccepted listener to create tickets (not Tasks)  
☐ One ticket per sold labour item with `sold_minutes` locked  
☐ Set `is_baseline_locked = 1` on created tickets  
☐ Set `root_ticket_id` = self on each created ticket  
☐ Store `ticket_id` on QuoteTask records  
☐ Link tasks.ticket_id for backward compatibility  
☐ Ensure sold fields immutable  
☐ Add regression tests  
☐ Add tests for duplicate prevention  

---

# Sprint 5 — WorkItem Alignment

☐ Ensure sold tickets project to WorkItems  
☐ Ensure assignment logic unchanged  
☐ Validate queue integrity  

---

# Sprint 6 — SLA Agreement (if enabled)

☐ Create Agreement entity  
☐ Create Entitlement Ledger  
☐ Create Consumption records  
☐ Integrate with TimeEntry submit path  
☐ Add overage approval gate  

---

# Definition of Done (Global)

☐ No submitted time entry without ticket_id  
☐ Quote acceptance creates one ticket per sold labour item  
☐ All project work traceable to tickets  
☐ Backward compatibility verified  
☐ Full stress-test suite passes  

---

# Hard Stops

If any of the following occur, stop implementation:

- Submitted time can exist without ticket_id.
- Baseline sold values can be edited post-acceptance.
- Backfill mutates historical time values.
- Duplicate tickets created for same quote task.
- Multiple primary containers assigned to one ticket.
