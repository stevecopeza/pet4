STATUS: AUTHORITATIVE — IMPLEMENTATION REQUIRED
SCOPE: Ticket Backbone Correction
VERSION: v1

# PET Ticket Backbone — Documentation Pack (v1)

This pack defines the **authoritative correction plan** required to enforce the invariant:

> **All person work activity must be tied to a Ticket.**

This is a **docs-first, implementation-orchestrating** pack intended to be used to drive TRAE implementation work with minimal ambiguity.

## Documents

1. `01_Ticket_Backbone_Principles_and_Invariants_v1.md`  
2. `02_Ticket_Data_Model_and_Migrations_v1.md`  
3. `03_Ticket_Lifecycle_and_State_Machines_v1.md`  
4. `04_Quote_to_Ticket_to_Project_Flow_v1.md`  
5. `05_Time_Entry_Ticket_Enforcement_v1.md`  
6. `06_Catalog_Roles_Rates_and_Snapshots_v1.md`  
7. `07_SLA_Agreement_Entitlement_Drawdown_v1.md`  
8. `08_Work_Orchestration_Queues_and_Assignment_v1.md`  
9. `09_Backward_Compatibility_and_Transition_Plan_v1.md`  
10. `10_Stress_Test_Scenarios_Ticket_Backbone_v1.md`  
11. `11_TRAE_Prompt_Ticket_Backbone_Implementation_ADD_ONLY_v1.md`

## Non-negotiables (applies to all docs)

- **Immutability of history:** accepted quotes and submitted time are never edited/deleted.
- **Corrections are additive:** compensating records, versioning, explicit adjustments.
- **Backward compatibility is mandatory:** users may skip versions.
- **Forward-only migrations:** no down migrations.
- **DDD boundaries:** Domain has no WP/DB; Infrastructure persists; UI has no business logic.
