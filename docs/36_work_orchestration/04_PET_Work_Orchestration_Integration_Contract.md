# PET Unified Work Orchestration -- Integration Contract v1.0

Ticket Integration: - Ticket must bind SLA snapshot on creation. - SLA
countdown calculated via Calendar Engine. - Waiting status pauses SLA
clock.

Project Integration: - Project Tasks surfaced via WorkItem projection. -
Variance warning triggered if SLA overrides delay task.

Capacity Integration: - Capacity calendar drives availability ranking. -
Over-allocation triggers warning event.

Escalation Integration: - SLAWarning and SLABreach events elevate
priority_score. - EscalationTriggered event logged in activity feed.

KPI Integration: - SLA compliance KPIs read from ticket + SLA
snapshot. - Context-switch metrics feed advisory layer.

Invariant Precedence: 1. Contract Snapshot 2. SLA Snapshot 3. Calendar
Snapshot 4. Priority Engine 5. UI State
