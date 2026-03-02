# PET Unified Work Orchestration -- Phased Implementation Plan v1.0

Status: Execution Plan Scope: Unified Work Pane, Department Queue, SLA &
Capacity Integration

PHASE 1 -- READ-ONLY AGGREGATION LAYER - Build unified "My Work" query
service - Aggregate: - Assigned Project Tasks - Assigned Tickets - SLA
countdown display - No priority scoring yet - No department queue pickup
logic Deliverable: Unified dashboard without behaviour changes.

PHASE 2 -- DEPARTMENT QUEUE MODEL - Implement DepartmentQueue entity -
Ticket default routing to department - Direct assignment support -
Introduce Operational Escalation Lead role - Implement unassigned
timeout notification Deliverable: Functional queue with escalation
layers.

PHASE 3 -- SLA CLOCK & PRIORITY ENGINE - Ticket SLA clock state
tracking - Pause/resume logic - Composite priority scoring engine -
Countdown timers in UI - Escalation event dispatch Deliverable:
Deterministic prioritisation across projects + tickets.

PHASE 4 -- CAPACITY & CONFLICT AWARENESS - Integrate capacity calendar -
Over-allocation detection - Overtime metadata flags - Conflict warnings
(individual + manager) Deliverable: Work pane becomes capacity-aware.

PHASE 5 -- METRICS & ADVISORY SIGNALS - Context-switch tracking - Mean
time to pickup - Escalation rate per department - SLA risk exposure
dashboard hooks Deliverable: Operational intelligence layer.

Execution Order: Phase 1 → 2 → 3 → 4 → 5
