# Wave 1 Migration Manifest

## Scope
This wave resolves the highest-risk overlap around:
- ticket architecture
- ticket lifecycle
- commercial handoff
- project delivery
- support/helpdesk vs project-delivery boundary
- SLA interaction boundary

## Actions

### REPLACE
1. docs/00_foundations/02_Ticket_Architecture_Decisions_v1.md
   - with docs/ToBeMoved/corrected/02_Ticket_Architecture_Decisions_v2.md

2. docs/03_domain_model/03_Ticket_Lifecycle_and_State_Machines_v1.md
   - with docs/ToBeMoved/corrected/03_Ticket_Lifecycle_and_State_Machines_v2.md

3. docs/07_commercial/04_Quote_to_Ticket_to_Project_Flow_v1.md
   - with docs/ToBeMoved/corrected/04_Quote_to_Ticket_to_Project_Flow_v2.md

4. docs/06_features/project_delivery.md
   - with docs/ToBeMoved/corrected/project_delivery_v2.md

5. docs/06_features/helpdesk_and_sla.md
   - with docs/ToBeMoved/corrected/helpdesk_and_sla_boundary_v2.md

### MARK SUPERSEDED
Add supersession headers to:
- docs/03_domain_model/03_state_machines.md
- docs/06_features/commercial_transition_rules.md
- docs/07_commercial/Delivery Handoff_ From Sold Quote to PM-Managed Project.md

## Intent
This wave does not redesign PET.
It clarifies authority, removes overlap, and aligns lifecycle, commercial, and delivery boundaries with PET principles.

## Expected Result
After migration:
- one authoritative ticket-architecture decisions document exists
- one authoritative ticket lifecycle/state-machine document exists
- one authoritative commercial quote→ticket→project handoff document exists
- project delivery feature guidance points to the ticket model, not legacy tasks
- helpdesk/SLA/project-delivery boundaries are explicit
