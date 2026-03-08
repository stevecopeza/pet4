clear

You are executing PET SLA Automation and Work Orchestration Hardening
Phase.

You must follow PET architectural invariants:

-   Domain rules in Domain layer only
-   Idempotent event handling
-   Strict immutability
-   Forward-only migrations
-   No WP logic in Domain

DO NOT redesign. IMPLEMENT ONLY per documentation.

==================================================== STEP 1 --- SLA
HARDENING ====================================================

Reference:
docs/15_implementation_blueprint/pre_demo_execution/05_sla_automation_memo.md
AND SLA Automation Hardening Addendum v1.0

Tasks:

1.  Verify/Create sla_clock_state migration:
    -   UNIQUE(ticket_id)
    -   Proper indexes
2.  Implement SlaAutomationService::evaluate():
    -   SELECT ... FOR UPDATE
    -   Compare persisted vs calculated state
    -   Dispatch events ONLY on transition
    -   Persist state before releasing lock
3.  Implement scheduler-safe batch evaluation:
    -   Only active SLA tickets
    -   Pagination safe
4.  Add required unit + integration tests.

==================================================== STEP 2 --- WORK
ORCHESTRATION HARDENING
====================================================

Reference: 08_Work_Orchestration_Queues_and_Assignment_v1.md AND Work
Orchestration v1 Hardening Specification

Tasks:

1.  Enforce UNIQUE(source_type, source_id, context_version)
2.  Implement idempotent TicketCreatedEvent → WorkItem projection
3.  Enforce assignment invariants (exactly one of team/employee)
4.  Implement deterministic PriorityScoringService
5.  Add projection + assignment + priority tests

====================================================

Constraints:

-   ADDITIVE implementation only
-   No documentation edits
-   No redesign
-   All domain invariants tested
-   Backward compatibility preserved

If ambiguity arises: - Halt - Cite doc - Request bounded clarification

Execution order is mandatory.
