STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# PET — TRAE Ticket Backbone Execution Checklist (v1)

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

# Sprint 4 — Quote Draft Tickets

☐ Hook into labour quote creation  
☐ Create draft tickets idempotently  
☐ Persist ticket_id on quote tasks  
☐ Add tests for duplicate prevention  

---

# Sprint 5 — Quote Acceptance Alignment

☐ Extend QuoteAccepted listener  
☐ Lock baseline tickets  
☐ Clone execution tickets  
☐ Link tasks.ticket_id  
☐ Ensure sold fields immutable  
☐ Add regression tests  

---

# Sprint 6 — WorkItem Alignment

☐ Ensure execution tickets project to WorkItems  
☐ Ensure assignment logic unchanged  
☐ Validate queue integrity  

---

# Sprint 7 — SLA Agreement (if enabled)

☐ Create Agreement entity  
☐ Create Entitlement Ledger  
☐ Create Consumption records  
☐ Integrate with TimeEntry submit path  
☐ Add overage approval gate  

---

# Definition of Done (Global)

☐ No submitted time entry without ticket_id  
☐ All new labour quote items create tickets  
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
